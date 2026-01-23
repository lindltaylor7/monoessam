<?php

use App\Http\Controllers\AreaController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\CafeController;
use App\Http\Controllers\ClothController;
use App\Http\Controllers\DealershipController;
use App\Http\Controllers\DinnerController;
use App\Http\Controllers\DishCategoryController;
use App\Http\Controllers\DishController;
use App\Http\Controllers\FoodController;
use App\Http\Controllers\GuardController;
use App\Http\Controllers\HeadcountController;
use App\Http\Controllers\IngredientCategoryController;
use App\Http\Controllers\IngredientController;
use App\Http\Controllers\LogisticController;
use App\Http\Controllers\ManagementController;
use App\Http\Controllers\MineController;
use App\Http\Controllers\PeriodController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProviderController;
use App\Http\Controllers\ReportSalesController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\SubdealershipController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// ============================================================================
// RUTAS PÚBLICAS
// ============================================================================
Route::get('/', function () {
    return Inertia::render('auth/Login');
})->name('home');

// Utilidad de desarrollo (considerar remover en producción)
Route::get('/migrate', function () {
    Artisan::call('migrate');
    dd('migrated!');
});

// ============================================================================
// RUTAS AUTENTICADAS
// ============================================================================
Route::middleware(['auth', 'verified'])->group(function () {

    // Dashboard
    Route::get('dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    // ========================================================================
    // GESTIÓN DE USUARIOS Y PERMISOS
    // ========================================================================


    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', [UsersController::class, 'index'])->name('index');
        Route::post('/', [UsersController::class, 'store'])->name('store');
        Route::put('{id}', [UsersController::class, 'update'])->name('update');
        Route::delete('{id}', [UsersController::class, 'destroy'])->name('destroy');
        Route::get('ban/{id}', [UsersController::class, 'banUser'])->name('ban');
        Route::get('blacklist/{id}', [UsersController::class, 'blacklist'])->name('blacklist');
        Route::get('assigned/{id}', [UsersController::class, 'assignedUsers'])->name('assigned');
    });


    Route::prefix('roles')->name('roles.')->group(function () {
        Route::get('/', [RoleController::class, 'index'])->name('index');
        Route::post('/', [RoleController::class, 'store'])->name('store');
        Route::post('permissions', [PermissionController::class, 'rolePermissions'])->name('permissions');
        Route::post('user', [PermissionController::class, 'roleUser'])->name('user');
    });

    Route::prefix('permissions')->name('permissions.')->group(function () {
        Route::get('/', [PermissionController::class, 'index'])->name('index');
        Route::post('/', [PermissionController::class, 'store'])->name('store');
        Route::put('{id}', [PermissionController::class, 'update'])->name('update');
        Route::delete('{id}', [PermissionController::class, 'destroy'])->name('destroy');
        Route::post('user/{id}', [PermissionController::class, 'userPermissions'])->name('user.update');
    });

    // ========================================================================
    // GUARDIAS Y ROLES DE GUARDIA
    // ========================================================================
    Route::prefix('guards')->name('guards.')->group(function () {
        Route::post('/', [GuardController::class, 'store'])->name('store');
        Route::post('roles', [GuardController::class, 'insertGuardRoles'])->name('roles.store');
        Route::delete('roles/{id}', [GuardController::class, 'deleteGuardRoles'])->name('roles.destroy');
        Route::post('roles/user', [GuardController::class, 'insertGuardRolesUser'])->name('roles.user.store');
        Route::delete('roles/user/{id}', [GuardController::class, 'deleteGuardRolesUser'])->name('roles.user.destroy');
        Route::delete('{id}', [GuardController::class, 'destroy'])->name('destroy');
    });

    // ========================================================================
    // PERSONAL
    // ========================================================================
    Route::prefix('staff')->name('staff.')->group(function () {
        Route::get('/', [StaffController::class, 'index'])->name('index');
        Route::post('/', [StaffController::class, 'store'])->name('store');
        Route::put('{id}', [StaffController::class, 'update'])->name('update');
        Route::delete('{id}', [StaffController::class, 'destroy'])->name('destroy');
        Route::get('ban/{id}', [StaffController::class, 'banStaff'])->name('ban');
        Route::post('/update-status', [StaffController::class, 'updateStatusStaff'])->name('update-status');
        Route::post('/upload-file', [StaffController::class, 'uploadFile'])->name('upload-file');
        Route::post('/upload-filedate', [StaffController::class, 'uploadFileDate'])->name('update-filedate');
        Route::delete('/delete-file/{id}', [StaffController::class, 'deleteFile'])->name('delete-file');
    });

    Route::prefix('headcount')->name('headcount.')->group(function () {
        Route::get('/', [HeadcountController::class, 'index'])->name('index');
        Route::post('/', [HeadcountController::class, 'store'])->name('store');
        Route::get('ban/{id}', [HeadcountController::class, 'banUser'])->name('ban');
        Route::get('blacklist/{id}', [HeadcountController::class, 'blacklist'])->name('blacklist');
        Route::get('assigned/{id}', [HeadcountController::class, 'assignedUsers'])->name('assigned');
    });

    // ========================================================================
    // ÁREAS Y UBICACIONES
    // ========================================================================
    Route::prefix('areas')->name('areas.')->group(function () {
        Route::post('/', [AreaController::class, 'store'])->name('store');
        Route::delete('{id}', [AreaController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('mines')->name('mines.')->group(function () {
        Route::post('/', [MineController::class, 'store'])->name('store');
        Route::get('search/{word}', [MineController::class, 'search'])->name('search');
        Route::post('serviceables', [MineController::class, 'mineServiceables'])->name('serviceables');
    });

    Route::prefix('units')->name('units.')->group(function () {
        Route::post('/', [UnitController::class, 'store'])->name('store');
        Route::get('search/{word}', [UnitController::class, 'search'])->name('search');
        Route::post('serviceables', [UnitController::class, 'unitServiceables'])->name('serviceables');
    });

    Route::prefix('cafes')->name('cafes.')->group(function () {
        Route::post('/', [CafeController::class, 'store'])->name('store');
        Route::get('{id}', [CafeController::class, 'show'])->name('show');
        Route::post('serviceables', [CafeController::class, 'cafeServiceables'])->name('serviceables');
        Route::get('{id}/export-headcount', [CafeController::class, 'exportHeadcount'])->name('export-headcount');
    });

    Route::prefix('dealerships')->name('dealerships.')->group(function () {
        Route::get('/', [DealershipController::class, 'index'])->name('index');
        Route::post('/', [DealershipController::class, 'store'])->name('store');
    });

    Route::prefix('subdealerships')->name('subdealerships.')->group(function () {
        Route::get('/', [SubdealershipController::class, 'index'])->name('index');
        Route::post('/', [SubdealershipController::class, 'store'])->name('store');
        Route::get('/{subdealership}', [SubdealershipController::class, 'show'])->name('show');
        Route::put('/{subdealership}', [SubdealershipController::class, 'update'])->name('update');
        Route::delete('/{id}', [SubdealershipController::class, 'destroy'])->name('destroy');
    });

    // ========================================================================
    // GESTIÓN DE ALIMENTOS
    // ========================================================================
    Route::prefix('food')->name('food.')->group(function () {
        Route::get('/', [FoodController::class, 'index'])->name('index');
        Route::get('structure-menu', [FoodController::class, 'structure'])->name('structure');
    });

    Route::prefix('dishes')->name('dishes.')->group(function () {
        Route::get('search/{word}', [DishController::class, 'search'])->name('search');
    });

    Route::prefix('dish-categories')->name('dish-categories.')->group(function () {
        Route::post('/', [DishCategoryController::class, 'store'])->name('store');
        Route::delete('{id}', [DishCategoryController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('ingredients')->name('ingredients.')->group(function () {
        Route::get('/', [IngredientController::class, 'index'])->name('index');
    });

    Route::prefix('ingredient-categories')->name('ingredient-categories.')->group(function () {
        Route::post('/', [IngredientCategoryController::class, 'store'])->name('store');
        Route::delete('{id}', [IngredientCategoryController::class, 'destroy'])->name('destroy');
    });

    // ========================================================================
    // CENAS Y COMIDAS
    // ========================================================================
    Route::prefix('dinners')->name('dinners.')->group(function () {
        Route::get('/', [DinnerController::class, 'index'])->name('index');
        Route::post('/', [DinnerController::class, 'store'])->name('store');
        Route::put('{id}', [DinnerController::class, 'update'])->name('update');
        Route::delete('{id}', [DinnerController::class, 'destroy'])->name('destroy');
        Route::post('excel', [DinnerController::class, 'excel'])->name('excel');
        Route::get('search/{word}/{id}', [DinnerController::class, 'search'])->name('search');
    });

    // ========================================================================
    // SERVICIOS
    // ========================================================================
    Route::prefix('services')->name('services.')->group(function () {
        Route::get('/', [ServiceController::class, 'index'])->name('index');
        Route::get('list', [ServiceController::class, 'list'])->name('list');
        Route::post('/', [ServiceController::class, 'store'])->name('store');
        Route::delete('{id}', [ServiceController::class, 'destroy'])->name('destroy');
        Route::put('prices', [ServiceController::class, 'updatePrices'])->name('update-prices');
    });

    // ========================================================================
    // PROVEEDORES
    // ========================================================================
    Route::prefix('providers')->name('providers.')->group(function () {
        Route::get('/', [ProviderController::class, 'index'])->name('index');
        Route::post('/', [ProviderController::class, 'store'])->name('store');
        Route::post('assign', [ProviderController::class, 'assign'])->name('assign');
    });

    // ========================================================================
    // NEGOCIOS
    // ========================================================================
    Route::prefix('businesses')->name('businesses.')->group(function () {
        Route::get('/', [BusinessController::class, 'index'])->name('index');
        Route::post('services', [BusinessController::class, 'businessServices'])->name('services');
    });

    // ========================================================================
    // VENTAS
    // ========================================================================
    Route::prefix('sales')->name('sales.')->group(function () {
        Route::get('/', [SaleController::class, 'index'])->name('index');
        Route::post('/', [SaleController::class, 'store'])->name('store');
        Route::get('pagination/{offset}', [SaleController::class, 'pagination'])->name('pagination');
        Route::get('report/{dateInitial}/{datFinal}', [SaleController::class, 'report'])->name('report');
        Route::get('print-ticket/{ticketId}/{businessId}', [SaleController::class, 'printTest'])->name('print-ticket');
    });

    // ========================================================================
    // ROPA Y PERFILES
    // ========================================================================
    Route::prefix('clothes')->name('clothes.')->group(function () {
        Route::get('/', [ClothController::class, 'index'])->name('index');
        Route::get('/manage', [ClothController::class, 'manage'])->name('manage');
        Route::post('/', [ClothController::class, 'store'])->name('store');
        Route::delete('/{id}', [ClothController::class, 'destroy'])->name('destroy');
        Route::post('/assign-role', [ClothController::class, 'assignRole'])->name('assign-role');
        Route::post('/staff-size', [ClothController::class, 'updateStaffSize'])->name('staff-size');
    });

    Route::prefix('reportsales')->name('reportsales.')->group(function () {
        Route::get('/', [ReportSalesController::class, 'index'])->name('index');
        Route::delete('{id}', [ReportSalesController::class, 'destroy'])->name('destroy');
        Route::get('export', [ReportSalesController::class, 'export'])->name('export');
    });


    // ========================================================================
    // PERÍODOS
    // ========================================================================
    Route::prefix('periods')->name('periods.')->group(function () {
        Route::post('/', [PeriodController::class, 'store'])->name('store');
        Route::delete('{id}', [PeriodController::class, 'destroy'])->name('destroy');
        Route::put('user/{id}', [PeriodController::class, 'periodUser'])->name('user');
    });

    // ========================================================================
    // OTRAS PÁGINAS
    // ========================================================================
    Route::get('management', [ManagementController::class, 'index'])->name('management');
    Route::get('logistics', [LogisticController::class, 'index'])->name('logistics');

    // ========================================================================
    // UTILIDADES
    // ========================================================================
    Route::get('qr/{id}', function ($id) {
        $arrayProducts = [
            1 => ['id' => 1, 'name' => 'Laptop Dell XPS 13', 'url' => '/products/1'],
            2 => ['id' => 2, 'name' => 'Proyector Epson', 'url' => '/products/2'],
            3 => ['id' => 3, 'name' => 'Impresora HP LaserJet', 'url' => '/products/3'],
            4 => ['id' => 4, 'name' => 'Monitor Samsung 24"', 'url' => '/products/4'],
            5 => ['id' => 5, 'name' => 'Teclado Mecánico Logitech', 'url' => '/products/5'],
            6 => ['id' => 6, 'name' => 'Mouse Inalámbrico Logitech', 'url' => '/products/6'],
            7 => ['id' => 7, 'name' => 'Disco Duro Externo Seagate', 'url' => '/products/7'],
            8 => ['id' => 8, 'name' => 'Router TP-Link Archer C6', 'url' => '/products/8'],
        ];

        if (!array_key_exists($id, $arrayProducts)) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        return response()->json($arrayProducts[$id]);
    })->name('qr.show');
});

// ============================================================================
// ARCHIVOS DE RUTAS ADICIONALES
// ============================================================================
require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
