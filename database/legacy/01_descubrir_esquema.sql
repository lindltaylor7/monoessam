/* ============================================================================
   FASE 1 - DESCUBRIR DONDE VIVE EL RECETARIO EN SQL SERVER (Tiburon)

   Correr en SSMS contra la base del sistema viejo, con la base ya seleccionada
   (USE [NombreDeLaBase] o eligiendola en el desplegable).

   QUE BUSCAMOS

   El import de Laravel (App\Imports\DishRecipesImport) necesita UNA fila por
   cada par plato-insumo, con dos columnas:

       cNomPlato   nombre del plato
       cNomProd    nombre del insumo

   El nombre del plato ya sabemos de donde sale: la tabla que alimento
   `stg_plato` tiene nCodPlato y cNomPlato. Lo que falta ubicar es el DETALLE
   de receta: la tabla que cruza plato con producto. Por la convencion que se
   ve en la migracion (GenTBasProd), las maestras son GenT* y el detalle suele
   ser GenD* o similar.

   Cada consulta esta numerada. Corre las 5 y pegame los resultados; con eso
   armo la consulta de extraccion exacta en 02_exportar_recetas.sql.
   ============================================================================ */


/* ----------------------------------------------------------------------------
   1. Inventario: todas las tablas con su conteo de filas
   El detalle de receta deberia ser de las mas pobladas (cientos de miles).
   ---------------------------------------------------------------------------- */
SELECT
    s.name                          AS esquema,
    t.name                          AS tabla,
    SUM(p.rows)                     AS filas
FROM sys.tables t
JOIN sys.schemas s   ON s.schema_id = t.schema_id
JOIN sys.partitions p ON p.object_id = t.object_id AND p.index_id IN (0, 1)
GROUP BY s.name, t.name
ORDER BY SUM(p.rows) DESC;


/* ----------------------------------------------------------------------------
   2. Toda columna que mencione "Plato"
   Sirve para ver que tablas cuelgan del catalogo de platos.
   ---------------------------------------------------------------------------- */
SELECT
    s.name          AS esquema,
    t.name          AS tabla,
    c.name          AS columna,
    ty.name         AS tipo,
    c.max_length    AS largo
FROM sys.columns c
JOIN sys.tables t   ON t.object_id = c.object_id
JOIN sys.schemas s  ON s.schema_id = t.schema_id
JOIN sys.types ty   ON ty.user_type_id = c.user_type_id
WHERE c.name LIKE '%Plato%'
ORDER BY t.name, c.column_id;


/* ----------------------------------------------------------------------------
   3. Toda columna que mencione producto / insumo / articulo
   El insumo puede llamarse cNomProd, cNomInsumo, cDesArticulo... no se asume.
   ---------------------------------------------------------------------------- */
SELECT
    s.name          AS esquema,
    t.name          AS tabla,
    c.name          AS columna,
    ty.name         AS tipo
FROM sys.columns c
JOIN sys.tables t   ON t.object_id = c.object_id
JOIN sys.schemas s  ON s.schema_id = t.schema_id
JOIN sys.types ty   ON ty.user_type_id = c.user_type_id
WHERE c.name LIKE '%Prod%'
   OR c.name LIKE '%Insum%'
   OR c.name LIKE '%Articul%'
   OR c.name LIKE '%Ingred%'
ORDER BY t.name, c.column_id;


/* ----------------------------------------------------------------------------
   4. LA CLAVE: tablas que tienen a la vez una columna de plato Y una de
      producto. Esa es la receta (plato x insumo). Si devuelve una sola fila,
      ya esta identificada.
   ---------------------------------------------------------------------------- */
SELECT
    s.name                                  AS esquema,
    t.name                                  AS tabla,
    SUM(p.rows)                             AS filas,
    STUFF((
        SELECT ', ' + c2.name
        FROM sys.columns c2
        WHERE c2.object_id = t.object_id
        ORDER BY c2.column_id
        FOR XML PATH(''), TYPE
    ).value('.', 'nvarchar(max)'), 1, 2, '') AS columnas
FROM sys.tables t
JOIN sys.schemas s    ON s.schema_id = t.schema_id
JOIN sys.partitions p ON p.object_id = t.object_id AND p.index_id IN (0, 1)
WHERE EXISTS (SELECT 1 FROM sys.columns c
              WHERE c.object_id = t.object_id AND c.name LIKE '%Plato%')
  AND EXISTS (SELECT 1 FROM sys.columns c
              WHERE c.object_id = t.object_id
                AND (c.name LIKE '%Prod%' OR c.name LIKE '%Insum%'
                     OR c.name LIKE '%Articul%' OR c.name LIKE '%Ingred%'))
GROUP BY s.name, t.name, t.object_id
ORDER BY SUM(p.rows) DESC;


/* ----------------------------------------------------------------------------
   5. La maestra de platos, para confirmar que es la que alimento stg_plato
      (debe tener nCodPlato y cNomPlato, y rondar las 17,000 filas)
   ---------------------------------------------------------------------------- */
SELECT
    s.name      AS esquema,
    t.name      AS tabla,
    SUM(p.rows) AS filas
FROM sys.tables t
JOIN sys.schemas s    ON s.schema_id = t.schema_id
JOIN sys.partitions p ON p.object_id = t.object_id AND p.index_id IN (0, 1)
WHERE EXISTS (SELECT 1 FROM sys.columns c
              WHERE c.object_id = t.object_id AND c.name = 'cNomPlato')
GROUP BY s.name, t.name
ORDER BY SUM(p.rows) DESC;
