ALTER TABLE stations
    ADD COLUMN IF NOT EXISTS river_role VARCHAR(16) NOT NULL DEFAULT 'upstream' AFTER is_primary;

UPDATE stations SET river_role = 'upstream' WHERE provider_station_code IN ('P.67', 'P.103');
UPDATE stations SET river_role = 'primary', is_primary = 1 WHERE provider_station_code = 'P.1';

INSERT INTO stations
    (provider, provider_station_id, provider_station_code, display_name_en, display_name_th,
     river_name_en, river_name_th, latitude, longitude, gauge_zero_msl, bank_level_msl,
     enabled, is_primary, river_role, sort_order)
VALUES
    ('cmflood', 'disaster:CMI01', 'CMI01', 'Chalermprakiat / 2nd Ring Road Bridge, San Phi Suea', 'สะพานสมโภชเชียงใหม่ 700ปี ต.สันผีเสือ', 'Ping River', 'แม่น้ำปิง', 18.8442300, 98.9852200, 300.300000, 308.831000, 1, 0, 'upstream', 30),
    ('cmflood', 'disaster:CMI02', 'CMI02', 'Khua Sri Wiang Ping Bridge', 'สะพานขัวสรีเวียงพิงค์', 'Ping River', 'แม่น้ำปิง', 18.8098590, 99.0033430, 300.231000, 306.099000, 1, 0, 'upstream', 40),
    ('cmflood', 'water_levels:22', 'FBP.2', 'Mengrai Bridge', 'สะพานเม็งราย', 'Ping River', 'แม่น้ำปิง', 18.7661870, 99.0032910, 294.200000, 303.135000, 1, 0, 'downstream', 60),
    ('cmflood', 'disaster:CMI03', 'CMI03', 'Police Region 5 Bridge', 'สะพานตำรวจภาค 5', 'Ping River', 'แม่น้ำปิง', 18.7601230, 98.9972160, 299.620000, 303.235000, 1, 0, 'downstream', 70),
    ('cmflood', 'water_levels:21', 'FBP.3', 'Chiang Mai 700-Year Anniversary Bridge, Pa Daet', 'สะพานสมโภชเชียงใหม่ 700ปี ต.ป่าแดด', 'Ping River', 'แม่น้ำปิง', 18.7457000, 98.9846700, 292.930000, 302.886000, 1, 0, 'downstream', 80),
    ('cmflood', 'flagship:ST.16', 'P.104', 'Third Ring Road Bridge, Pa Daet', 'สะพานวงแหวนรอบสาม ต.ป่าแดด', 'Ping River', 'แม่น้ำปิง', 18.7199730, 98.9867370, 294.050000, 301.118000, 1, 0, 'downstream', 90)
ON DUPLICATE KEY UPDATE
    provider_station_id = VALUES(provider_station_id),
    display_name_en = VALUES(display_name_en), display_name_th = VALUES(display_name_th),
    latitude = VALUES(latitude), longitude = VALUES(longitude),
    gauge_zero_msl = VALUES(gauge_zero_msl), bank_level_msl = VALUES(bank_level_msl),
    enabled = VALUES(enabled), is_primary = VALUES(is_primary), river_role = VALUES(river_role),
    sort_order = VALUES(sort_order);

UPDATE stations SET
    display_name_en = 'Mae Faek Bridge', display_name_th = 'สะพานแม่แฝก',
    river_role = 'upstream', sort_order = 10
WHERE provider_station_code = 'P.67';

UPDATE stations SET
    display_name_en = 'Third Ring Road Bridge, Don Kaeo', display_name_th = 'สะพานวงแหวนรอบสาม ต.ดอนแก้ว',
    river_role = 'upstream', sort_order = 20
WHERE provider_station_code = 'P.103';

UPDATE stations SET
    display_name_en = 'Nawarat Bridge', display_name_th = 'สะพานนวรัฐ',
    river_role = 'primary', is_primary = 1, sort_order = 50
WHERE provider_station_code = 'P.1';
