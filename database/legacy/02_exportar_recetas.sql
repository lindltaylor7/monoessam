/* ============================================================================
   FASE 2 - EXTRAER EL RECETARIO PARA REIMPORTARLO EN LARAVEL

   Correr en SSMS contra la base del sistema viejo, DESPUES de haber corrido
   01_descubrir_esquema.sql y reemplazado los nombres marcados con  <<< >>>.

   QUE PRODUCE

   Una fila por cada par plato-insumo, con las columnas que espera
   App\Imports\DishRecipesImport:

       cNomPlato    nombre del plato   -> Dish::firstOrCreate(['name' => ...])
       cNomProd     nombre del insumo  -> Ingredient::firstOrCreate(['name' => ...])

   Los nombres son la clave de todo. El import empareja POR NOMBRE, nunca por
   id, y por eso es inmune al problema que nos trajo hasta aqui: los ids del
   sistema viejo (nCodPlato) se cargaron sin traducir en dish_recipes.dish_id y
   cada receta quedo colgada de un plato ajeno. Exportando nombres eso no puede
   volver a pasar.

   Las columnas de cantidad van al final: hoy el import las ignora y escribe 0,
   pero conviene traerlas en el mismo archivo para no repetir la extraccion
   cuando se extienda el importador.

   COMO SACAR EL ARCHIVO DESDE SSMS

   Opcion recomendada (respeta tildes y comas):
     clic derecho sobre la base > Tasks > Export Data...
     Destino: Microsoft Excel, o "Flat File" con Code page = 65001 (UTF-8)

   Opcion rapida (ojo con el encoding):
     Query > Results To > Results to File, y guardar como .csv
     Si las tildes salen mal, usar la opcion de arriba.

   El archivo final se sube por la pantalla de Platos y Recetas, boton Importar.
   ============================================================================ */


/* ----------------------------------------------------------------------------
   CONSULTA PRINCIPAL

   Reemplazar los  <<< >>>  con lo que haya devuelto la consulta 4 de la fase 1:

     <<<DETALLE>>>        tabla que cruza plato con producto  (ej. GenDPlato)
     <<<MAESTRA_PLATO>>>  maestra de platos                   (ej. GenTPlato)
     <<<MAESTRA_PROD>>>   maestra de productos                (ej. GenTProd)
     <<<COL_COD_PROD>>>   columna de codigo de producto en el detalle y en su
                          maestra (ej. nCodProd)
     <<<COL_NOM_PROD>>>   columna con el NOMBRE del producto  (ej. cNomProd)
   ---------------------------------------------------------------------------- */

SELECT
    LTRIM(RTRIM(pl.cNomPlato))              AS cNomPlato,
    LTRIM(RTRIM(pr.<<<COL_NOM_PROD>>>))     AS cNomProd,

    -- Cantidades: hoy el importador no las usa, pero vienen para no reextraer.
    -- Ajustar los nombres a lo que tenga el detalle (nCantidad, nPeso, nMerma...).
    det.nCantidad                           AS nCantidad,
    det.nMerma                               AS nMerma,

    -- Trazabilidad: permite auditar despues contra mig_tiburon.map_dish
    pl.nCodPlato                            AS nCodPlato
FROM <<<DETALLE>>>              AS det
JOIN <<<MAESTRA_PLATO>>>        AS pl ON pl.nCodPlato        = det.nCodPlato
JOIN <<<MAESTRA_PROD>>>         AS pr ON pr.<<<COL_COD_PROD>>> = det.<<<COL_COD_PROD>>>
WHERE pl.cNomPlato IS NOT NULL
  AND LTRIM(RTRIM(pl.cNomPlato)) <> ''
  AND pr.<<<COL_NOM_PROD>>> IS NOT NULL
  AND LTRIM(RTRIM(pr.<<<COL_NOM_PROD>>>)) <> ''
  -- Si la maestra marca bajas con cEstado, descomentar para excluirlas.
  -- Ojo: el catalogo que ya esta migrado incluye platos inactivos, asi que
  -- filtrar aqui puede dejar sin receta a platos que si existen en Laravel.
  -- AND pl.cEstado = 'A'
ORDER BY pl.nCodPlato, pr.<<<COL_NOM_PROD>>>;


/* ----------------------------------------------------------------------------
   VERIFICACIONES PREVIAS

   Correr estas tres ANTES de exportar. Si los numeros no se parecen a los de
   referencia, el detalle identificado no es el correcto.
   ---------------------------------------------------------------------------- */

-- a) Cuantas filas saldrian. Referencia: el respaldo de beta tiene 159,103
--    lineas de receta, asi que esperar ese orden de magnitud.
SELECT COUNT(*) AS filas_a_exportar
FROM <<<DETALLE>>> AS det
JOIN <<<MAESTRA_PLATO>>> AS pl ON pl.nCodPlato = det.nCodPlato;

-- b) Cuantos platos distintos tienen receta. Referencia: 16,579.
SELECT COUNT(DISTINCT det.nCodPlato) AS platos_con_receta
FROM <<<DETALLE>>> AS det;

-- c) Control de sentido: un plato de pollo deberia traer pollo.
--    Si esto sale vacio o con insumos ajenos, la tabla de detalle no es.
SELECT TOP 20
    pl.nCodPlato,
    pl.cNomPlato,
    pr.<<<COL_NOM_PROD>>> AS insumo
FROM <<<DETALLE>>>       AS det
JOIN <<<MAESTRA_PLATO>>> AS pl ON pl.nCodPlato        = det.nCodPlato
JOIN <<<MAESTRA_PROD>>>  AS pr ON pr.<<<COL_COD_PROD>>> = det.<<<COL_COD_PROD>>>
WHERE pl.cNomPlato LIKE '%POLLO AL ROMERO%'
ORDER BY pl.nCodPlato;
/*  Esperado, segun el respaldo de beta para nCodPlato 57:
    Ajos Arequipeño Extra, Romero FRESCOS EXTRAS, Pollo Eviscerado x 1.80,
    Ajinomoto envasado, Comino molido IRAN, OREGANO seco LIMPIO              */
