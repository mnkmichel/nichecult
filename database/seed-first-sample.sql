-- ============================================================
-- Seed: 5 perfumes + first sample set
-- Run AFTER sample-sets-migration.sql
-- ============================================================

-- 0. Add size_ml column (skip if it already exists)
ALTER TABLE perfumes
  ADD COLUMN size_ml SMALLINT NULL AFTER image_path;

-- 1. Insert the 5 perfumes
INSERT INTO perfumes (name, brand_name, description, size_ml, price_cents, discount_percent, is_active)
VALUES
  ('Arancia di Capri', 'Acqua di Parma',   'Eine frische, sonnige Komposition mit Bergamotte und sizilianischer Orange.', 100, 14900, 0, 1),
  ('Oud',             'Acqua di Parma',   'Ein elegantes orientalisches Oud mit warmem, holzigem Charakter.',           75,  24500, 0, 1),
  ('Oajan',           'Parfums de Marly', 'Exotisch-würziger Duft mit Safran, Rose und warmem Holz.',                 125, 32000, 0, 1),
  ('Original Santal', 'Creed',            'Zeitloser Sandelholzduft mit weichen, cremigen Nuancen.',                  100, 41500, 0, 1),
  ('Accento',         'Xerjoff',          'Frischer, eleganter Duft mit Zitruspräludium und blumigem Herzen.',        100, 27800, 0, 1);

-- 2. Create the first sample set
INSERT INTO sample_sets (title, description, status)
VALUES ('Erste Duftselektion', 'Ihre persönliche erste Auswahl kuratierter Parfums.', 'active');

-- 3. Link the 5 perfumes to the sample set (sort_order 1–5)
--    Uses the last inserted IDs — safe when run immediately after the INSERTs above.
INSERT INTO sample_set_items (sample_set_id, perfume_id, sort_order)
SELECT
  ss.id                        AS sample_set_id,
  p.id                         AS perfume_id,
  ROW_NUMBER() OVER (ORDER BY p.id) AS sort_order
FROM sample_sets ss
CROSS JOIN perfumes p
WHERE ss.title = 'Erste Duftselektion'
  AND p.name IN ('Arancia di Capri', 'Oud', 'Oajan', 'Original Santal', 'Accento')
  AND p.brand_name IN ('Acqua di Parma', 'Acqua di Parma', 'Parfums de Marly', 'Creed', 'Xerjoff');
