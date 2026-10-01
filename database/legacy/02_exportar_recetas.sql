/* ============================================================================
   FASE 2 - EXTRAER EL RECETARIO DESDE SQL SERVER (Tiburon)

   Tablas ya identificadas con la fase 1. No hay nada que reemplazar: correr
   tal cual en SSMS, con la base del sistema viejo seleccionada.

       PlaDplato        169,767 filas   detalle receta (plato x producto)
       PlaMPlato         16,951 filas   maestra de platos
       ComMProductos      2,524 filas   maestra de productos

   QUE PRODUCE

   Una fila por par plato-insumo con las columnas que lee
   App\Imports\DishRecipesImport. El importador empareja POR NOMBRE con
   firstOrCreate, nunca por id, y ahi esta la gracia: es inmune al problema que
   origino todo esto. En la migracion anterior el nCodPlato se cargo tal cual
   en dish_recipes.dish_id sin traducirlo, asi que el numero cuadraba con la FK
   por coincidencia de rango y cada receta quedo colgada de un plato ajeno.
   Exportando nombres eso no puede repetirse.

   CONTROL DE REFERENCIA

   El respaldo de beta del 29-09 tiene 159,103 lineas de receta sobre 16,579
   platos. PlaDplato tiene 169,767 filas, asi que la consulta principal deberia
   devolver algo entre esos dos numeros. Si da mucho menos, algun JOIN esta
   descartando filas: correr las verificaciones del final antes de exportar.

   COMO SACAR EL ARCHIVO

   Recomendado (respeta tildes):
     clic derecho en la base > Tasks > Export Data... > destino Microsoft Excel
     o "Flat File" con Code page = 65001 (UTF-8)

   Rapido (verificar tildes despues):
     Query > Results To > Results to File, guardar como .csv

   Luego se sube por Platos y Recetas > Importar.
   ============================================================================ */


/* ----------------------------------------------------------------------------
   CONSULTA PRINCIPAL - esto es lo que se exporta
   ---------------------------------------------------------------------------- */
SELECT
    LTRIM(RTRIM(pl.cNomPlato))      AS cNomPlato,
    LTRIM(RTRIM(pr.cNomProd))       AS cNomProd,

    -- Cantidades. nCantBas es la cantidad en unidad base (la que sirve para el
    -- recetario); nCantReq es la requerida para el numero de raciones del plato.
    -- Se llevan las dos mas nNumRac para poder derivar la porcion unitaria.
    det.nCantBas                    AS nCantBas,
    det.nCantReq                    AS nCantReq,
    det.nTipUndBas                  AS nTipUndBas,
    pl.nNumRac                      AS nNumRac,
    pl.nVolumen                     AS nVolumen,

    -- Trazabilidad: permite auditar el resultado contra mig_tiburon.map_dish
    pl.nCodPlato                    AS nCodPlato,
    pr.nCodProd                     AS nCodProd
FROM dbo.PlaDplato      AS det
JOIN dbo.PlaMPlato      AS pl ON pl.nCodPlato = det.nCodPlato
JOIN dbo.ComMProductos  AS pr ON pr.nCodProd  = det.nCodProd
WHERE LTRIM(RTRIM(ISNULL(pl.cNomPlato, ''))) <> ''
  AND LTRIM(RTRIM(ISNULL(pr.cNomProd,  ''))) <> ''
  -- NO filtrar por cEstado: el catalogo ya migrado a Laravel incluye platos
  -- dados de baja, y excluirlos aqui los dejaria sin receta. Si se quiere
  -- solo lo vigente, descomentar y comparar el conteo con la verificacion (a).
  -- AND pl.cEstado = 'A'
  -- AND det.cEstado = 'A'
ORDER BY pl.nCodPlato, pr.cNomProd;


/* ============================================================================
   VERIFICACIONES - correr ANTES de exportar
   ============================================================================ */

/* (a) Volumen. Esperado: entre 159,103 y 169,767.
       La columna perdidas dice cuantas filas del detalle se caen por no tener
       plato o producto en su maestra; si es alta, hay que revisar por que. */
SELECT
    (SELECT COUNT(*) FROM dbo.PlaDplato)                        AS detalle_total,
    (SELECT COUNT(*)
       FROM dbo.PlaDplato det
       JOIN dbo.PlaMPlato pl     ON pl.nCodPlato = det.nCodPlato
       JOIN dbo.ComMProductos pr ON pr.nCodProd  = det.nCodProd) AS filas_a_exportar,
    (SELECT COUNT(DISTINCT det.nCodPlato) FROM dbo.PlaDplato det) AS platos_con_receta;


/* (b) Que se pierde en cada JOIN, por separado */
SELECT 'sin plato en PlaMPlato' AS motivo, COUNT(*) AS filas
FROM dbo.PlaDplato det
WHERE NOT EXISTS (SELECT 1 FROM dbo.PlaMPlato pl WHERE pl.nCodPlato = det.nCodPlato)
UNION ALL
SELECT 'sin producto en ComMProductos', COUNT(*)
FROM dbo.PlaDplato det
WHERE NOT EXISTS (SELECT 1 FROM dbo.ComMProductos pr WHERE pr.nCodProd = det.nCodProd);


/* (c) Control de sentido. Esta es la prueba que de verdad importa: si estas
       recetas salen coherentes, la extraccion es correcta.

       Esperado para nCodPlato 57 (POLLO AL ROMERO), segun el respaldo de beta:
         Ajos Arequipeño Extra, Romero FRESCOS EXTRAS, Pollo Eviscerado x 1.80,
         Ajinomoto envasado, Comino molido IRAN, OREGANO seco LIMPIO

       Esperado para nCodPlato 270 (MAZAMORRA DE CALABAZA):
         Calabaza Madura, Canela entera, Chancaca, Clavo de Olor, Leche GLORIA,
         Maizena                                                              */
SELECT
    pl.nCodPlato,
    LTRIM(RTRIM(pl.cNomPlato)) AS plato,
    LTRIM(RTRIM(pr.cNomProd))  AS insumo,
    det.nCantBas
FROM dbo.PlaDplato      AS det
JOIN dbo.PlaMPlato      AS pl ON pl.nCodPlato = det.nCodPlato
JOIN dbo.ComMProductos  AS pr ON pr.nCodProd  = det.nCodProd
WHERE pl.nCodPlato IN (57, 270, 1, 12000)
ORDER BY pl.nCodPlato, pr.cNomProd;


/* (d) Cuantos platos quedarian con receta vacia (estan en la maestra pero no
       tienen ninguna linea en el detalle). Referencia: beta tiene 494 asi. */
SELECT COUNT(*) AS platos_sin_receta
FROM dbo.PlaMPlato pl
WHERE NOT EXISTS (SELECT 1 FROM dbo.PlaDplato det WHERE det.nCodPlato = pl.nCodPlato);


/* ============================================================================
   OPCIONAL - tablas que completan el recetario y que conviene exportar aparte
   si mas adelante se quieren mermas, calorias y unidades reales:

     BdMerma        1,915   nCodProd, cProducto        merma por insumo
     BdEquiKal      2,050   nCodProd, cNomProd         equivalencias / kcal
     BdNutrikal     1,079   cProdKal                   nutricion
     PlaDFacNutri   1,612   nCodProd                   factores nutricionales
     GenTUndMed        74                              unidades de medida
     GenTUsoProd       47                              nTipUsoProd (uso del insumo)
   ============================================================================ */
