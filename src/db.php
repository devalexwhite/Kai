<?php
declare(strict_types=1);

/**
 * Return a singleton PDO instance connected to the SQLite database.
 * Bootstraps the schema on first connection.
 */
function get_db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dbDir  = __DIR__ . '/../database';
    $dbPath = $dbDir . '/kai.sqlite';

    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0755, true);
    }

    $pdo = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA foreign_keys = ON');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cities (
            id    INTEGER PRIMARY KEY AUTOINCREMENT,
            name  TEXT NOT NULL,
            state TEXT NOT NULL,
            UNIQUE(name, state)
        );
    
        CREATE TABLE IF NOT EXISTS users (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            name       TEXT    NOT NULL,
            email      TEXT    NOT NULL UNIQUE COLLATE NOCASE,
            city_id    INTEGER NOT NULL REFERENCES cities(id),
            password   TEXT    NOT NULL,
            created_at TEXT    NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS remember_tokens (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            selector   TEXT    NOT NULL UNIQUE,
            token_hash TEXT    NOT NULL,
            expires_at TEXT    NOT NULL,
            created_at TEXT    NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS user_groups (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            name        TEXT    NOT NULL,
            description TEXT    NOT NULL DEFAULT '',
            city_id     INTEGER NOT NULL REFERENCES cities(id),
            creator_id  INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            created_at  TEXT    NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS group_members (
            id        INTEGER PRIMARY KEY AUTOINCREMENT,
            group_id  INTEGER NOT NULL REFERENCES user_groups(id) ON DELETE CASCADE,
            user_id   INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            joined_at TEXT    NOT NULL DEFAULT (datetime('now')),
            UNIQUE(group_id, user_id)
        );

        CREATE INDEX IF NOT EXISTS idx_remember_selector   ON remember_tokens(selector);
        CREATE INDEX IF NOT EXISTS idx_users_email         ON users(email);
        CREATE INDEX IF NOT EXISTS idx_group_members_group ON group_members(group_id);
        CREATE INDEX IF NOT EXISTS idx_group_members_user  ON group_members(user_id);
        CREATE INDEX IF NOT EXISTS idx_user_groups_city    ON user_groups(city_id);
        CREATE INDEX IF NOT EXISTS idx_user_groups_creator ON user_groups(creator_id);
    ");

    // Seed cities — INSERT OR IGNORE is idempotent (skips rows that already exist)
    $pdo->exec("
        INSERT OR IGNORE INTO cities (name, state) VALUES
        ('Birmingham','AL'),('Huntsville','AL'),('Mobile','AL'),('Montgomery','AL'),
        ('Anchorage','AK'),
        ('Chandler','AZ'),('Gilbert','AZ'),('Glendale','AZ'),('Mesa','AZ'),
        ('Peoria','AZ'),('Phoenix','AZ'),('Scottsdale','AZ'),('Surprise','AZ'),
        ('Tempe','AZ'),('Tucson','AZ'),
        ('Fayetteville','AR'),('Fort Smith','AR'),('Little Rock','AR'),
        ('Anaheim','CA'),('Antioch','CA'),('Bakersfield','CA'),('Berkeley','CA'),
        ('Chula Vista','CA'),('Clovis','CA'),('Concord','CA'),('Corona','CA'),
        ('Costa Mesa','CA'),('Downey','CA'),('El Monte','CA'),('Elk Grove','CA'),
        ('Escondido','CA'),('Fontana','CA'),('Fremont','CA'),('Fresno','CA'),
        ('Fullerton','CA'),('Garden Grove','CA'),('Glendale','CA'),
        ('Hayward','CA'),('Huntington Beach','CA'),('Inglewood','CA'),
        ('Irvine','CA'),('Lancaster','CA'),('Long Beach','CA'),('Los Angeles','CA'),
        ('Modesto','CA'),('Moreno Valley','CA'),('Norwalk','CA'),('Oakland','CA'),
        ('Oceanside','CA'),('Ontario','CA'),('Orange','CA'),('Oxnard','CA'),
        ('Palmdale','CA'),('Pasadena','CA'),('Pomona','CA'),
        ('Rancho Cucamonga','CA'),('Riverside','CA'),('Roseville','CA'),
        ('Sacramento','CA'),('Salinas','CA'),('San Bernardino','CA'),
        ('San Diego','CA'),('San Francisco','CA'),('San Jose','CA'),
        ('Santa Ana','CA'),('Santa Clara','CA'),('Santa Clarita','CA'),
        ('Santa Rosa','CA'),('Simi Valley','CA'),('Stockton','CA'),
        ('Sunnyvale','CA'),('Thousand Oaks','CA'),('Torrance','CA'),('Visalia','CA'),
        ('Arvada','CO'),('Aurora','CO'),('Centennial','CO'),('Colorado Springs','CO'),
        ('Denver','CO'),('Fort Collins','CO'),('Lakewood','CO'),('Pueblo','CO'),
        ('Thornton','CO'),('Westminster','CO'),
        ('Bridgeport','CT'),('Hartford','CT'),('New Haven','CT'),('Stamford','CT'),('Waterbury','CT'),
        ('Washington','DC'),
        ('Cape Coral','FL'),('Clearwater','FL'),('Coral Springs','FL'),
        ('Fort Lauderdale','FL'),('Gainesville','FL'),('Hialeah','FL'),
        ('Hollywood','FL'),('Jacksonville','FL'),('Lakeland','FL'),('Miami','FL'),
        ('Miramar','FL'),('Orlando','FL'),('Palm Bay','FL'),('Pembroke Pines','FL'),
        ('Pompano Beach','FL'),('Port St. Lucie','FL'),('St. Petersburg','FL'),
        ('Tallahassee','FL'),('Tampa','FL'),('West Palm Beach','FL'),
        ('Albany','GA'),('Athens','GA'),('Atlanta','GA'),('Augusta','GA'),
        ('Columbus','GA'),('Macon','GA'),('Roswell','GA'),('Sandy Springs','GA'),('Savannah','GA'),
        ('Honolulu','HI'),
        ('Boise','ID'),('Meridian','ID'),('Nampa','ID'),
        ('Aurora','IL'),('Chicago','IL'),('Elgin','IL'),('Joliet','IL'),
        ('Naperville','IL'),('Peoria','IL'),('Rockford','IL'),('Springfield','IL'),('Waukegan','IL'),
        ('Bloomington','IN'),('Carmel','IN'),('Evansville','IN'),('Fishers','IN'),
        ('Fort Wayne','IN'),('Indianapolis','IN'),('South Bend','IN'),
        ('Cedar Rapids','IA'),('Davenport','IA'),('Des Moines','IA'),('Sioux City','IA'),
        ('Kansas City','KS'),('Olathe','KS'),('Overland Park','KS'),('Topeka','KS'),('Wichita','KS'),
        ('Bowling Green','KY'),('Lexington','KY'),('Louisville','KY'),
        ('Baton Rouge','LA'),('Lafayette','LA'),('Metairie','LA'),('New Orleans','LA'),('Shreveport','LA'),
        ('Baltimore','MD'),('Frederick','MD'),('Rockville','MD'),
        ('Boston','MA'),('Cambridge','MA'),('Lowell','MA'),('Springfield','MA'),('Worcester','MA'),
        ('Ann Arbor','MI'),('Clinton Township','MI'),('Dearborn','MI'),('Detroit','MI'),
        ('Flint','MI'),('Grand Rapids','MI'),('Lansing','MI'),('Livonia','MI'),
        ('Sterling Heights','MI'),('Warren','MI'),
        ('Bloomington','MN'),('Duluth','MN'),('Minneapolis','MN'),('Rochester','MN'),('St. Paul','MN'),
        ('Gulfport','MS'),('Jackson','MS'),
        ('Columbia','MO'),('Independence','MO'),('Kansas City','MO'),
        ('Springfield','MO'),('St. Louis','MO'),
        ('Bellevue','NE'),('Lincoln','NE'),('Omaha','NE'),
        ('Henderson','NV'),('Las Vegas','NV'),('North Las Vegas','NV'),('Reno','NV'),('Sparks','NV'),
        ('Manchester','NH'),('Nashua','NH'),
        ('Camden','NJ'),('Clifton','NJ'),('Edison','NJ'),('Elizabeth','NJ'),
        ('Jersey City','NJ'),('Newark','NJ'),('Paterson','NJ'),('Trenton','NJ'),
        ('Albuquerque','NM'),('Las Cruces','NM'),('Rio Rancho','NM'),
        ('Albany','NY'),('Buffalo','NY'),('New York','NY'),('Rochester','NY'),('Syracuse','NY'),('Yonkers','NY'),
        ('Cary','NC'),('Charlotte','NC'),('Concord','NC'),('Durham','NC'),('Fayetteville','NC'),
        ('Greensboro','NC'),('High Point','NC'),('Raleigh','NC'),('Wilmington','NC'),('Winston-Salem','NC'),
        ('Bismarck','ND'),('Fargo','ND'),
        ('Akron','OH'),('Canton','OH'),('Cincinnati','OH'),('Cleveland','OH'),
        ('Columbus','OH'),('Dayton','OH'),('Parma','OH'),('Toledo','OH'),
        ('Broken Arrow','OK'),('Lawton','OK'),('Norman','OK'),('Oklahoma City','OK'),('Tulsa','OK'),
        ('Bend','OR'),('Eugene','OR'),('Gresham','OR'),('Hillsboro','OR'),
        ('Medford','OR'),('Portland','OR'),('Salem','OR'),
        ('Allentown','PA'),('Erie','PA'),('Philadelphia','PA'),('Pittsburgh','PA'),('Reading','PA'),('Scranton','PA'),
        ('Cranston','RI'),('Providence','RI'),('Warwick','RI'),
        ('Charleston','SC'),('Columbia','SC'),('Mount Pleasant','SC'),('North Charleston','SC'),('Rock Hill','SC'),
        ('Rapid City','SD'),('Sioux Falls','SD'),
        ('Chattanooga','TN'),('Clarksville','TN'),('Franklin','TN'),('Jackson','TN'),
        ('Knoxville','TN'),('Memphis','TN'),('Murfreesboro','TN'),('Nashville','TN'),
        ('Abilene','TX'),('Amarillo','TX'),('Arlington','TX'),('Austin','TX'),
        ('Beaumont','TX'),('Brownsville','TX'),('Carrollton','TX'),('Corpus Christi','TX'),
        ('Dallas','TX'),('Denton','TX'),('El Paso','TX'),('Fort Worth','TX'),
        ('Frisco','TX'),('Garland','TX'),('Grand Prairie','TX'),('Houston','TX'),
        ('Irving','TX'),('Killeen','TX'),('Laredo','TX'),('Lewisville','TX'),
        ('Lubbock','TX'),('McAllen','TX'),('McKinney','TX'),('Mesquite','TX'),
        ('Midland','TX'),('Odessa','TX'),('Pasadena','TX'),('Pearland','TX'),
        ('Plano','TX'),('Richardson','TX'),('Round Rock','TX'),('San Antonio','TX'),('Waco','TX'),
        ('Layton','UT'),('Orem','UT'),('Provo','UT'),('Salt Lake City','UT'),
        ('Sandy','UT'),('St. George','UT'),('West Jordan','UT'),('West Valley City','UT'),
        ('Alexandria','VA'),('Chesapeake','VA'),('Fredericksburg','VA'),('Hampton','VA'),
        ('Newport News','VA'),('Norfolk','VA'),('Richmond','VA'),('Roanoke','VA'),('Virginia Beach','VA'),
        ('Bellingham','WA'),('Bellevue','WA'),('Everett','WA'),('Kent','WA'),
        ('Kirkland','WA'),('Renton','WA'),('Seattle','WA'),('Spokane','WA'),
        ('Spokane Valley','WA'),('Tacoma','WA'),('Vancouver','WA'),
        ('Charleston','WV'),('Huntington','WV'),
        ('Appleton','WI'),('Green Bay','WI'),('Kenosha','WI'),('Madison','WI'),
        ('Milwaukee','WI'),('Racine','WI'),('Waukesha','WI'),
        ('Casper','WY'),('Cheyenne','WY')
    ");

    return $pdo;
}
