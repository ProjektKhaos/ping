SET NAMES utf8mb4;

INSERT INTO stations
    (provider, provider_station_id, provider_station_code, display_name_en, display_name_th, river_name_en, river_name_th, latitude, longitude, gauge_zero_msl, bank_level_msl, enabled, is_primary, river_role, sort_order)
VALUES
    ('thaiwater', '3247', 'P.67', 'Mae Faek Bridge', 'สะพานแม่แฝก', 'Ping River', 'แม่น้ำปิง', 19.0098500, 98.9597400, 315.929993, 318.930000, 1, 0, 'upstream', 10),
    ('thaiwater', '504679', 'P.103', 'Third Ring Road Bridge, Don Kaeo', 'สะพานวงแหวนรอบสาม ต.ดอนแก้ว', 'Ping River', 'แม่น้ำปิง', 18.8665100, 98.9781880, 300.890015, 307.690000, 1, 0, 'upstream', 20),
    ('cmflood', 'disaster:CMI01', 'CMI01', 'Chalermprakiat / 2nd Ring Road Bridge, San Phi Suea', 'สะพานสมโภชเชียงใหม่ 700ปี ต.สันผีเสือ', 'Ping River', 'แม่น้ำปิง', 18.8442300, 98.9852200, 300.300000, 308.831000, 1, 0, 'upstream', 30),
    ('cmflood', 'disaster:CMI02', 'CMI02', 'Khua Sri Wiang Ping Bridge', 'สะพานขัวสรีเวียงพิงค์', 'Ping River', 'แม่น้ำปิง', 18.8098590, 99.0033430, 300.231000, 306.099000, 1, 0, 'upstream', 40),
    ('thaiwater', '3226', 'P.1', 'Nawarat Bridge', 'สะพานนวรัฐ', 'Ping River', 'แม่น้ำปิง', 18.7869610, 99.0050890, 300.500000, 304.200000, 1, 1, 'primary', 50),
    ('cmflood', 'water_levels:22', 'FBP.2', 'Mengrai Bridge', 'สะพานเม็งราย', 'Ping River', 'แม่น้ำปิง', 18.7661870, 99.0032910, 294.200000, 303.135000, 1, 0, 'downstream', 60),
    ('cmflood', 'disaster:CMI03', 'CMI03', 'Police Region 5 Bridge', 'สะพานตำรวจภาค 5', 'Ping River', 'แม่น้ำปิง', 18.7601230, 98.9972160, 299.620000, 303.235000, 1, 0, 'downstream', 70),
    ('cmflood', 'water_levels:21', 'FBP.3', 'Chiang Mai 700-Year Anniversary Bridge, Pa Daet', 'สะพานสมโภชเชียงใหม่ 700ปี ต.ป่าแดด', 'Ping River', 'แม่น้ำปิง', 18.7457000, 98.9846700, 292.930000, 302.886000, 1, 0, 'downstream', 80),
    ('cmflood', 'flagship:ST.16', 'P.104', 'Third Ring Road Bridge, Pa Daet', 'สะพานวงแหวนรอบสาม ต.ป่าแดด', 'Ping River', 'แม่น้ำปิง', 18.7199730, 98.9867370, 294.050000, 301.118000, 1, 0, 'downstream', 90)
ON DUPLICATE KEY UPDATE
    provider_station_id = VALUES(provider_station_id),
    display_name_en = VALUES(display_name_en),
    display_name_th = VALUES(display_name_th),
    latitude = VALUES(latitude),
    longitude = VALUES(longitude),
    gauge_zero_msl = VALUES(gauge_zero_msl),
    bank_level_msl = VALUES(bank_level_msl),
    enabled = VALUES(enabled),
    is_primary = VALUES(is_primary),
    river_role = VALUES(river_role),
    sort_order = VALUES(sort_order);

INSERT INTO station_thresholds
    (station_id, threshold_type, value_m, datum, source_name, source_url, verified_at, notes, active)
SELECT id, 'warning', 3.7000, 'gauge', 'Royal Irrigation Department / ThaiWater', 'https://www.hydro-1.net/Data/STATION/P.1.html', '2026-08-21', 'Water begins to exceed the documented low-bank warning level at P.1.', 1
FROM stations WHERE provider_station_code = 'P.1'
ON DUPLICATE KEY UPDATE value_m = VALUES(value_m), source_name = VALUES(source_name), source_url = VALUES(source_url), verified_at = VALUES(verified_at), notes = VALUES(notes), active = 1;

INSERT INTO station_thresholds
    (station_id, threshold_type, value_m, datum, source_name, source_url, verified_at, notes, active)
SELECT id, 'overflow', 3.7000, 'gauge', 'Royal Irrigation Department / ThaiWater', 'https://www.hydro-1.net/Data/STATION/P.1.html', '2026-08-21', 'Documented critical warning/bank level. Preserved separately from the app severity mapping.', 1
FROM stations WHERE provider_station_code = 'P.1'
ON DUPLICATE KEY UPDATE value_m = VALUES(value_m), source_name = VALUES(source_name), source_url = VALUES(source_url), verified_at = VALUES(verified_at), notes = VALUES(notes), active = 1;

INSERT INTO station_thresholds
    (station_id, threshold_type, value_m, datum, source_name, source_url, verified_at, notes, active)
SELECT id, 'critical', 4.2000, 'gauge', 'Chiang Mai Provincial Government', 'https://www.chiangmai.go.th/managing/public/M1/D24Nov2025204507.pdf', '2026-08-21', 'The provincial response document begins its medium-overflow impact band at P.1 = 4.20 m.', 1
FROM stations WHERE provider_station_code = 'P.1'
ON DUPLICATE KEY UPDATE value_m = VALUES(value_m), source_name = VALUES(source_name), source_url = VALUES(source_url), verified_at = VALUES(verified_at), notes = VALUES(notes), active = 1;

INSERT INTO forecast_zones
    (code, display_name_en, display_name_th, latitude, longitude, zone_type, affects_risk, enabled, sort_order, notes)
VALUES
    ('P.67', 'Mae Tae upstream point', 'จุดต้นน้ำบ้านแม่แต', 19.0098500, 98.9597400, 'upstream', 1, 1, 10, 'Point forecast at verified P.67 station coordinates; it does not represent the full catchment.'),
    ('P.103', 'Don Kaeo upstream point', 'จุดต้นน้ำดอนแก้ว', 18.8665100, 98.9781880, 'upstream', 1, 1, 20, 'Point forecast at verified P.103 station coordinates; it does not represent the full catchment.'),
    ('P.1', 'Chiang Mai city reference', 'จุดอ้างอิงเมืองเชียงใหม่', 18.7869610, 99.0050890, 'city', 0, 1, 30, 'City reference only; excluded from upstream Weather Risk.')
ON DUPLICATE KEY UPDATE
    display_name_en = VALUES(display_name_en),
    display_name_th = VALUES(display_name_th),
    latitude = VALUES(latitude),
    longitude = VALUES(longitude),
    zone_type = VALUES(zone_type),
    affects_risk = VALUES(affects_risk),
    enabled = VALUES(enabled),
    sort_order = VALUES(sort_order),
    notes = VALUES(notes);
