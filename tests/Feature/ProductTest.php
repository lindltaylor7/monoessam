<?php

use App\Models\Mercantil;
use App\Models\Mine;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Unit;
use App\Models\User;

/**
 * Crea un usuario + mercantil dentro de la misma mina (o de minas distintas si $sameMine=false),
 * replicando el criterio de scopedMercantilIds() en ProductController: Mercantil -> Unit -> mine_id.
 */
function makeUserAndMercantil(bool $sameMine = true): array
{
    $mine = Mine::factory()->create();
    $unit = Unit::factory()->create(['mine_id' => $mine->id]);
    $mercantil = Mercantil::factory()->create(['unit_id' => $unit->id]);

    $userMine = $sameMine ? $mine->id : Mine::factory()->create()->id;
    $user = User::factory()->create(['mine_id' => $userMine]);

    return [$user, $mercantil];
}

test('products page is displayed for authenticated users', function () {
    [$user, $mercantil] = makeUserAndMercantil();
    Product::factory()->create(['mercantil_id' => $mercantil->id]);

    $this->actingAs($user)
        ->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn($page) => $page->component('products/Index')->has('products', 1)->has('mercantiles'));
});

test('can create a product with marca and sku', function () {
    [$user, $mercantil] = makeUserAndMercantil();

    $payload = [
        'mercantil_id' => $mercantil->id,
        'name'         => 'Agua Mineral 500ml',
        'marca'        => 'San Luis',
        'sku'          => 'AGU-001',
        'category'     => 'Bebidas',
        'price'        => 2.5,
        'stock'        => 10,
        'is_active'    => true,
    ];

    $this->actingAs($user)->post(route('products.store'), $payload)->assertRedirect();

    // Regresión directa del bug reportado: antes el campo se validaba bajo la llave
    // "products_sku_unique" (no coincidía con lo que envía el frontend) y sku/marca
    // se guardaban como NULL silenciosamente.
    $this->assertDatabaseHas('products', [
        'name'  => 'Agua Mineral 500ml',
        'marca' => 'San Luis',
        'sku'   => 'AGU-001',
    ]);
});

test('can update a product including marca and sku', function () {
    [$user, $mercantil] = makeUserAndMercantil();
    $product = Product::factory()->create(['mercantil_id' => $mercantil->id, 'marca' => 'Vieja', 'sku' => 'OLD-1']);

    $payload = [
        'mercantil_id' => $mercantil->id,
        'name'         => $product->name,
        'marca'        => 'Nueva Marca',
        'sku'          => 'NEW-1',
        'category'     => $product->category,
        'price'        => $product->price,
        'stock'        => $product->stock,
        'is_active'    => true,
    ];

    $this->actingAs($user)->put(route('products.update', $product->id), $payload)->assertRedirect();

    $this->assertDatabaseHas('products', ['id' => $product->id, 'marca' => 'Nueva Marca', 'sku' => 'NEW-1']);
});

test('cannot create a product in a mercantil from another mine', function () {
    [$user, $mercantil] = makeUserAndMercantil(sameMine: false);

    $payload = [
        'mercantil_id' => $mercantil->id,
        'name'         => 'Producto Ajeno',
        'price'        => 1,
        'stock'        => 1,
        'is_active'    => true,
    ];

    $this->actingAs($user)->post(route('products.store'), $payload)->assertForbidden();
    $this->assertDatabaseMissing('products', ['name' => 'Producto Ajeno']);
});

test('cannot update a product belonging to another mine', function () {
    [$user, $mercantil] = makeUserAndMercantil(sameMine: false);
    $product = Product::factory()->create(['mercantil_id' => $mercantil->id, 'name' => 'Original']);

    $this->actingAs($user)
        ->put(route('products.update', $product->id), [
            'mercantil_id' => $mercantil->id,
            'name'         => 'Hackeado',
            'price'        => 1,
            'stock'        => 1,
            'is_active'    => true,
        ])
        ->assertForbidden();

    $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Original']);
});

test('cannot delete a product belonging to another mine', function () {
    [$user, $mercantil] = makeUserAndMercantil(sameMine: false);
    $product = Product::factory()->create(['mercantil_id' => $mercantil->id]);

    $this->actingAs($user)->delete(route('products.destroy', $product->id))->assertForbidden();
    $this->assertDatabaseHas('products', ['id' => $product->id]);
});

test('cannot adjust stock of a product belonging to another mine', function () {
    [$user, $mercantil] = makeUserAndMercantil(sameMine: false);
    $product = Product::factory()->create(['mercantil_id' => $mercantil->id, 'stock' => 5]);

    $this->actingAs($user)->patch(route('products.stock', $product->id), ['delta' => 10])->assertForbidden();
    $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 5]);
});

// ── Lotes / vencimientos ────────────────────────────────────────────────────

test('adding a batch creates it and increases the product total stock', function () {
    [$user, $mercantil] = makeUserAndMercantil();
    $product = Product::factory()->create(['mercantil_id' => $mercantil->id, 'stock' => 10]);

    $this->actingAs($user)
        ->post(route('products.batches.store', $product->id), [
            'batch_code'      => 'L-001',
            'quantity'        => 15,
            'expiration_date' => now()->addMonths(6)->toDateString(),
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('product_batches', ['product_id' => $product->id, 'batch_code' => 'L-001', 'quantity' => 15]);
    expect($product->fresh()->stock)->toBe(25); // 10 + 15
});

test('a batch expiring within 7 days is flagged expiring_soon and surfaces on the product', function () {
    [$user, $mercantil] = makeUserAndMercantil();
    $product = Product::factory()->create(['mercantil_id' => $mercantil->id]);

    $this->actingAs($user)->post(route('products.batches.store', $product->id), [
        'quantity'        => 5,
        'expiration_date' => now()->addDays(3)->toDateString(),
    ]);

    $batch = ProductBatch::where('product_id', $product->id)->first();
    expect($batch->expiration_status)->toBe('expiring_soon');

    $this->actingAs($user)
        ->get(route('products.index'))
        ->assertInertia(
            fn($page) => $page->where('products.0.worst_batch_status', 'expiring_soon'),
        );
});

test('a batch already expired is flagged expired and surfaces on the product', function () {
    [$user, $mercantil] = makeUserAndMercantil();
    $product = Product::factory()->create(['mercantil_id' => $mercantil->id]);

    $this->actingAs($user)->post(route('products.batches.store', $product->id), [
        'quantity'        => 5,
        'expiration_date' => now()->subDays(2)->toDateString(),
    ]);

    $batch = ProductBatch::where('product_id', $product->id)->first();
    expect($batch->expiration_status)->toBe('expired');

    $this->actingAs($user)
        ->get(route('products.index'))
        ->assertInertia(fn($page) => $page->where('products.0.worst_batch_status', 'expired'));
});

test('a batch expiring far in the future does not trigger an alert', function () {
    [$user, $mercantil] = makeUserAndMercantil();
    $product = Product::factory()->create(['mercantil_id' => $mercantil->id]);

    $this->actingAs($user)->post(route('products.batches.store', $product->id), [
        'quantity'        => 5,
        'expiration_date' => now()->addMonths(3)->toDateString(),
    ]);

    $batch = ProductBatch::where('product_id', $product->id)->first();
    expect($batch->expiration_status)->toBe('ok');

    $this->actingAs($user)
        ->get(route('products.index'))
        ->assertInertia(fn($page) => $page->where('products.0.worst_batch_status', null));
});

test('the worst status wins when a product has batches with mixed expiration states', function () {
    [$user, $mercantil] = makeUserAndMercantil();
    $product = Product::factory()->create(['mercantil_id' => $mercantil->id]);

    ProductBatch::factory()->for($product)->create(['expiration_date' => now()->addMonths(3)->toDateString()]); // ok
    ProductBatch::factory()->for($product)->create(['expiration_date' => now()->addDays(3)->toDateString()]);   // expiring_soon
    ProductBatch::factory()->for($product)->create(['expiration_date' => now()->subDays(1)->toDateString()]);   // expired

    $this->actingAs($user)
        ->get(route('products.index'))
        ->assertInertia(fn($page) => $page->where('products.0.worst_batch_status', 'expired'));
});

test('deleting a batch decrements the product stock and removes the batch', function () {
    [$user, $mercantil] = makeUserAndMercantil();
    $product = Product::factory()->create(['mercantil_id' => $mercantil->id, 'stock' => 10]);
    $batch = ProductBatch::factory()->for($product)->create(['quantity' => 4]);
    $product->update(['stock' => 14]); // simula que el lote ya fue sumado al stock

    $this->actingAs($user)->delete(route('products.batches.destroy', $batch->id))->assertRedirect();

    $this->assertDatabaseMissing('product_batches', ['id' => $batch->id]);
    expect($product->fresh()->stock)->toBe(10);
});

test('deleting a batch never leaves stock below zero', function () {
    [$user, $mercantil] = makeUserAndMercantil();
    $product = Product::factory()->create(['mercantil_id' => $mercantil->id, 'stock' => 2]);
    $batch = ProductBatch::factory()->for($product)->create(['quantity' => 10]);

    $this->actingAs($user)->delete(route('products.batches.destroy', $batch->id))->assertRedirect();

    expect($product->fresh()->stock)->toBe(0);
});

test('batch quantity must be a positive integer', function () {
    [$user, $mercantil] = makeUserAndMercantil();
    $product = Product::factory()->create(['mercantil_id' => $mercantil->id]);

    $this->actingAs($user)
        ->post(route('products.batches.store', $product->id), ['quantity' => 0, 'expiration_date' => now()->addMonth()->toDateString()])
        ->assertSessionHasErrors('quantity');

    $this->assertDatabaseCount('product_batches', 0);
});

test('cannot add a batch to a product belonging to another mine', function () {
    [$user, $mercantil] = makeUserAndMercantil(sameMine: false);
    $product = Product::factory()->create(['mercantil_id' => $mercantil->id]);

    $this->actingAs($user)
        ->post(route('products.batches.store', $product->id), ['quantity' => 5, 'expiration_date' => now()->addMonth()->toDateString()])
        ->assertForbidden();

    $this->assertDatabaseCount('product_batches', 0);
});

test('cannot delete a batch belonging to a product from another mine', function () {
    [$user, $mercantil] = makeUserAndMercantil(sameMine: false);
    $product = Product::factory()->create(['mercantil_id' => $mercantil->id]);
    $batch = ProductBatch::factory()->for($product)->create();

    $this->actingAs($user)->delete(route('products.batches.destroy', $batch->id))->assertForbidden();
    $this->assertDatabaseHas('product_batches', ['id' => $batch->id]);
});
