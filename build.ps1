# Laravel Project Build Script for Herd

Write-Host "🚀 Building eTaxi Laravel Project..." -ForegroundColor Cyan
Write-Host ""

# Step 1: Install PHP dependencies
Write-Host "📦 Installing Composer dependencies..." -ForegroundColor Yellow
composer install --no-interaction --prefer-dist --optimize-autoloader
if ($LASTEXITCODE -ne 0) {
    Write-Host "❌ Composer install failed" -ForegroundColor Red
    exit 1
}
Write-Host "✅ Composer dependencies installed" -ForegroundColor Green
Write-Host ""

# Step 2: Copy environment file
Write-Host "⚙️ Setting up environment..." -ForegroundColor Yellow
if (!(Test-Path ".env")) {
    Copy-Item ".env.example" ".env"
    Write-Host "✅ .env file created from .env.example" -ForegroundColor Green
} else {
    Write-Host "✅ .env file already exists" -ForegroundColor Green
}
Write-Host ""

# Step 3: Generate app key if needed
Write-Host "🔑 Generating app key..." -ForegroundColor Yellow
php artisan key:generate --force
Write-Host "✅ App key generated" -ForegroundColor Green
Write-Host ""

# Step 4: Install Node dependencies
Write-Host "📦 Installing Node dependencies..." -ForegroundColor Yellow
npm install --prefer-offline
if ($LASTEXITCODE -ne 0) {
    Write-Host "❌ npm install failed" -ForegroundColor Red
    exit 1
}
Write-Host "✅ Node dependencies installed" -ForegroundColor Green
Write-Host ""

# Step 5: Build frontend assets
Write-Host "🔨 Building frontend assets..." -ForegroundColor Yellow
npm run build
if ($LASTEXITCODE -ne 0) {
    Write-Host "❌ npm build failed" -ForegroundColor Red
    exit 1
}
Write-Host "✅ Frontend assets built" -ForegroundColor Green
Write-Host ""

# Step 6: Run migrations
Write-Host "🗄️ Running database migrations..." -ForegroundColor Yellow
php artisan migrate --force
Write-Host "✅ Database migrations completed" -ForegroundColor Green
Write-Host ""

Write-Host "🎉 Build completed successfully!" -ForegroundColor Green
Write-Host ""
Write-Host "📝 Next steps:" -ForegroundColor Cyan
Write-Host "   1. Run: php artisan serve" -ForegroundColor White
Write-Host "   2. Visit: http://localhost:8000" -ForegroundColor White
Write-Host ""
