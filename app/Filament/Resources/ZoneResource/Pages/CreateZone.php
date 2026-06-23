<?php

namespace App\Filament\Resources\ZoneResource\Pages;

use App\Filament\Resources\ZoneResource;
use App\Models\ZoneSurgeSlot;
use App\Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use MatanYadaev\EloquentSpatial\Objects\LineString;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Objects\Polygon;

class CreateZone extends CreateRecord
{
    protected static string $resource = ZoneResource::class;


    public ?string $mapBoundaries = null;


    protected array $dayFields = [
        0 => 'surge_slots_day_0', // Sunday
        1 => 'surge_slots_day_1', // Monday
        2 => 'surge_slots_day_2', // Tuesday
        3 => 'surge_slots_day_3', // Wednesday
        4 => 'surge_slots_day_4', // Thursday
        5 => 'surge_slots_day_5', // Friday
        6 => 'surge_slots_day_6', // Saturday
    ];


    #[On('boundaries-updated')]
    public function onBoundariesUpdated(string $boundaries): void
    {

        $this->mapBoundaries = $boundaries;
    }


    public function updatedMapBoundaries(?string $value): void
    {
        if ($value) {
        }
    }

    protected function resolveBoundariesJson(array $data): ?string
    {
        if (!empty($data['boundaries']) && $data['boundaries'] !== '[]') {
            if (is_string($data['boundaries'])) {
                return $data['boundaries'];
            }

            if (is_array($data['boundaries'])) {
                return json_encode($data['boundaries']);
            }
        }

        if (!empty($this->mapBoundaries) && $this->mapBoundaries !== '[]') {
            return $this->mapBoundaries;
        }

        $requestBoundaries = request()->input('boundaries');
        if (!empty($requestBoundaries) && $requestBoundaries !== '[]') {
            return is_string($requestBoundaries) ? $requestBoundaries : json_encode($requestBoundaries);
        }

        $requestMapBoundaries = request()->input('map_boundaries');
        if (!empty($requestMapBoundaries) && $requestMapBoundaries !== '[]') {
            return is_string($requestMapBoundaries) ? $requestMapBoundaries : json_encode($requestMapBoundaries);
        }

        return null;
    }

    protected function isValidBoundariesJson(?string $boundariesJson): bool
    {
        if (empty($boundariesJson) || $boundariesJson === '[]' || $boundariesJson === 'null') {
            return false;
        }

        $points = json_decode($boundariesJson, true);
        if (!is_array($points) || count($points) < 3) {
            return false;
        }

        foreach ($points as $point) {
            if (!is_array($point) || !isset($point['lat'], $point['lng'])) {
                return false;
            }

            if (!is_numeric($point['lat']) || !is_numeric($point['lng'])) {
                return false;
            }
        }

        return true;
    }

    protected function boundariesColumnRequiresValueOnInsert(): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        $column = DB::selectOne("\n            SELECT IS_NULLABLE\n            FROM information_schema.COLUMNS\n            WHERE TABLE_SCHEMA = DATABASE()\n              AND TABLE_NAME = 'zones'\n              AND COLUMN_NAME = 'boundaries'\n            LIMIT 1\n        ");

        if (!$column) {
            return false;
        }

        return strtoupper((string) ($column->IS_NULLABLE ?? 'YES')) === 'NO';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {

        // Get boundaries JSON string - we'll save it separately using raw SQL
        $boundariesJson = $this->resolveBoundariesJson($data);

        // Store boundaries JSON for later use in afterCreate
        if ($boundariesJson) {
            $this->mapBoundaries = $boundariesJson;
        }

        // For schemas where boundaries is NOT NULL (e.g., spatial-indexed polygon),
        // we must include a geometry value in the initial insert.
        if ($this->boundariesColumnRequiresValueOnInsert()) {
            $polygon = $this->convertBoundariesToPolygon($boundariesJson);

            if (!$polygon) {
                throw ValidationException::withMessages([
                    'boundaries' => 'Please draw a valid zone boundary with at least 3 points.'
                ]);
            }

            $data['boundaries'] = $polygon;
        } else {
            // Keep legacy flow for nullable schemas and apply with raw SQL after create.
            unset($data['boundaries']);
        }

        // Remove surge slot fields from data array - they're not database columns
        // and will be handled separately in afterCreate()
        foreach ($this->dayFields as $fieldName) {
            unset($data[$fieldName]);
        }

        return $data;
    }


    protected function convertBoundariesToPolygon(mixed $boundaries): ?Polygon
    {
        if ($boundaries instanceof Polygon) {
            return $boundaries;
        }

        $pointsArray = null;

        if (is_string($boundaries)) {
            $decoded = json_decode($boundaries, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $pointsArray = $decoded;
            }
        } elseif (is_array($boundaries)) {
            $pointsArray = $boundaries;
        }

        if (!is_array($pointsArray) || count($pointsArray) < 3) {
            return null;
        }

        try {
            $points = array_map(function ($point) {
                return new Point((float) $point['lat'], (float) $point['lng']);
            }, $pointsArray);

            if (
                $points[0]->latitude !== $points[count($points) - 1]->latitude ||
                $points[0]->longitude !== $points[count($points) - 1]->longitude
            ) {
                $points[] = $points[0];
            }

            return new Polygon([new LineString($points)]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function beforeCreate(): void
    {

        $data = $this->form->getState();

        $boundariesJson = $this->resolveBoundariesJson($data);
        if (!$this->isValidBoundariesJson($boundariesJson)) {
            throw ValidationException::withMessages([
                'boundaries' => 'Please draw a valid zone boundary with at least 3 points.'
            ]);
        }

        $this->mapBoundaries = $boundariesJson;

        if (isset($data['status']) && $data['status'] === true && isset($data['city_id'])) {
            $city = \App\Models\City::find($data['city_id']);

            if ($city && !$city->status) {
                throw ValidationException::withMessages([
                    'status' => 'City is inactive. Cannot create active zone in an inactive city.'
                ]);
            }
        }
    }

    protected function afterCreate(): void
    {
        // Save boundaries using raw SQL to avoid Polygon serialization issues
        $this->saveBoundaries();

        // Save surge slots
        $this->saveSurgeSlots();
    }

    protected function saveBoundaries(): void
    {
        if (empty($this->mapBoundaries) || $this->mapBoundaries === '[]' || $this->mapBoundaries === 'null') {

            return;
        }

        try {
            $points = json_decode($this->mapBoundaries, true);

            if (!$points || !is_array($points) || count($points) < 3) {
                return;
            }


            // Convert to WKT format (Well-Known Text)
            $wktPoints = [];
            foreach ($points as $point) {
                if (!isset($point['lat']) || !isset($point['lng'])) {

                    return;
                }
                $wktPoints[] = (float) $point['lng'] . ' ' . (float) $point['lat'];
            }

            // Close the polygon by adding the first point at the end
            if (count($wktPoints) > 0) {
                $wktPoints[] = $wktPoints[0];
            }

            $wkt = 'POLYGON((' . implode(', ', $wktPoints) . '))';


            // Update boundaries using raw SQL
            $affectedRows = DB::update(
                "UPDATE zones SET boundaries = ST_GeomFromText(?), updated_at = NOW() WHERE id = ?",
                [$wkt, $this->record->id]
            );


            // Verify the boundaries were saved correctly
            $verification = DB::selectOne(
                "SELECT ST_AsText(boundaries) as boundaries_text FROM zones WHERE id = ?",
                [$this->record->id]
            );
        } catch (\Exception $e) {
            throw $e; // Re-throw to prevent zone creation without boundaries
        }
    }


    protected function saveSurgeSlots(): void
    {
        $data = $this->form->getState();
        $zoneId = $this->record->id;

        foreach ($this->dayFields as $dayOfWeek => $fieldName) {
            $slots = $data[$fieldName] ?? [];

            foreach ($slots as $slot) {
                ZoneSurgeSlot::create([
                    'zone_id' => $zoneId,
                    'day_of_week' => $dayOfWeek,
                    'start_time' => $slot['start_time'],
                    'end_time' => $slot['end_time'],
                    'surge_multiplier' => $slot['surge_multiplier'],
                    'is_active' => $slot['is_active'] ?? true,
                ]);
            }
        }
    }
}
