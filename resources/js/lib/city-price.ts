// Precio vigente de un insumo para una ciudad de abastecimiento. Es la misma regla que
// Ingredient_city_provider::activeFor en el backend: el proveedor activo con precio de esa
// ciudad; si no hay, el activo de otra ciudad marcado como "prestado"; si tampoco, sin precio.
// El quebrado no guarda precios, así que Quebrados y Ciclos siempre costean con esto.

export interface CityPrice {
    price: number;
    cityId: number | null;
    borrowed: boolean;
    missing: boolean;
}

const isPriced = (a: any) => a?.is_active && parseFloat(a.cost_price) > 0;

export const cityPriceFor = (ingredient: any, cityId: number | null): CityPrice => {
    const priced = (ingredient?.assignments || []).filter(isPriced);
    const own = cityId ? priced.find((a: any) => Number(a.city_id) === Number(cityId)) : null;
    if (own) {
        return { price: parseFloat(own.cost_price), cityId: Number(own.city_id), borrowed: false, missing: false };
    }

    const other = [...priced].sort((a: any, b: any) => Number(a.city_id) - Number(b.city_id))[0];
    if (other) {
        return { price: parseFloat(other.cost_price), cityId: Number(other.city_id), borrowed: !!cityId, missing: false };
    }

    return { price: 0, cityId: null, borrowed: false, missing: true };
};

// Deja en la fila del quebrado el precio de la ciudad y recalcula su costo (precio por Kg).
export const applyCityPrice = (ingredient: any, cityId: number | null) => {
    const result = cityPriceFor(ingredient, cityId);
    ingredient.unit_price = result.price;
    ingredient.price_city_id = result.cityId;
    ingredient.price_borrowed = result.borrowed;
    ingredient.price_missing = result.missing;
    ingredient.cost = ((parseFloat(ingredient.gross_weight) || 0) / 1000) * result.price;
};

// Insumos de una receta sin proveedor activo con precio en la ciudad (los que en un ciclo de esa
// ciudad saldrían sin precio o con precio prestado).
export const ingredientsWithoutCityPrice = (ingredients: any[], cityId: number) =>
    (ingredients || []).filter((ing) => !(ing?.assignments || []).some((a: any) => isPriced(a) && Number(a.city_id) === Number(cityId)));
