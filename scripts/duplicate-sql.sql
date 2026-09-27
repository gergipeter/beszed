-- Duplicate each item 10 times using pure SQL
-- Usage: mysql -u root -proot beszed < duplicate-sql.sql

SET @games = 'kezdo,kirako,papagaj,parkereso,szotag,hallgasd,rimelo,mitunt,ikerhangok,melyik,kulonbseg,utasitas,zs,nagysag,szamol,ceruza,erzelmek,mondd,rimparok,tortenet,okoska,valogato,korus,arnyek,hol';

-- For each game not yet fully duplicated, create 10 copies of each item
INSERT INTO beszed_content_items (game, level, payload, active, source, status, created_at, updated_at)
SELECT game, level, payload, true, 'api', status, NOW(), NOW()
FROM (
  SELECT * FROM beszed_content_items WHERE source = 'seed'
  ORDER BY id
) AS items
CROSS JOIN (
  SELECT 1 AS n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
  UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9
) AS copies
WHERE FIND_IN_SET(items.game, @games) > 0;
