# json2db
PHP code to convert multiple deeply nested json files to sqlite db

- It navigate inside json structure to find info that can be mapped to tables and import them into SQLITE whithout having to know about the DB schema.
- The tool will also insert a column about the json level of the tuple and an unique id of the table.
- The first tuple of each file will be stored in the files table with any root info.
- If he finds a tuple that is already inserted it will not import them twice.
	- For this reason a parent child logic will not work. In order to reconstruct the links between the tables a path table is generated based on the sctucture of the imported json.


# Usage
Json2DB($JsonDir, $destDB, $appendToDB = false, $regexFilterArray = null, $lookup = null) 

$appendToDB can be set to true to append more files to the same db
$regexFilterArray can be used to filter jsons before creating the db
It uses regex based on the flattened version of the json (. is used as delimiter)
    
	$regexFilterArray =
    [
        '/^root.[0-9]+.name$/',
    ]
	
$lookup can be used to change json attribute name before importing into db

    $lookup = [
        
        'node' => 'name',
        'othernode' => 'name',
        'morenode'  => 'more',
    ];
	

# example
Consider the following json 
```json
{
	"donut":
	[
		{
		"id": "0001",
		"name": "Cake",
		"ppu": 0.55,
		"batters":
			{
				"batter":
					[
						{ "id": "1001", "type": "Regular" },
						{ "id": "1002", "type": "Chocolate" },
						{ "id": "1003", "type": "Blueberry" },
						{ "id": "1004", "type": "Devil's Food" }
					]
			},
		"topping":
			[
				{ "id": "5001", "type": "None" },
				{ "id": "5002", "type": "Glazed" },
				{ "id": "5005", "type": "Sugar" },
				{ "id": "5007", "type": "Powdered Sugar" },
				{ "id": "5006", "type": "Chocolate with Sprinkles" },
				{ "id": "5003", "type": "Chocolate" },
				{ "id": "5004", "type": "Maple" }
			]
		},
		{
		"id": "0002",
		"name": "Raised",
		"ppu": 0.55,
		"batters":
			{
				"batter":
					[
						{ "id": "1001", "type": "Regular" }
					]
			},
		"topping":
			[
				{ "id": "5001", "type": "None" },
				{ "id": "5002", "type": "Glazed" },
				{ "id": "5005", "type": "Sugar" },
				{ "id": "5003", "type": "Chocolate" },
				{ "id": "5004", "type": "Maple" }
			]
		},
		{
		"id": "0003",
		"name": "Old Fashioned",
		"ppu": 0.55,
		"batters":
			{
				"batter":
					[
						{ "id": "1001", "type": "Regular" },
						{ "id": "1002", "type": "Chocolate" }
					]
			},
		"topping":
			[
				{ "id": "5001", "type": "None" },
				{ "id": "5002", "type": "Glazed" },
				{ "id": "5003", "type": "Chocolate" },
				{ "id": "5004", "type": "Maple" }
			]
		}
	]
}
```
```sql
The following tables will be generated:

CREATE TABLE IF NOT EXISTS "files" (
	"pk"	INTEGER,
	"jsonLevel"	TEXT,
	"filename"	TEXT,
	PRIMARY KEY("pk")
);
CREATE TABLE IF NOT EXISTS "donut" (
	"pk"	INTEGER,
	"id"	TEXT,
	"name"	TEXT,
	"ppu"	TEXT,
	"jsonLevel"	TEXT,
	PRIMARY KEY("pk")
);
CREATE TABLE IF NOT EXISTS "batters.batter" (
	"pk"	INTEGER,
	"id"	TEXT,
	"type"	TEXT,
	"jsonLevel"	TEXT,
	PRIMARY KEY("pk")
);
CREATE TABLE IF NOT EXISTS "path_table" (
	"pk"	INTEGER,
	"pathId"	INTEGER,
	"pathLevel"	INTEGER,
	"lastPkLevel"	INTEGER,
	"nodeId"	INTEGER,
	"nodeTable"	TEXT,
	"parentId"	INTEGER,
	"parentTable"	TEXT,
	PRIMARY KEY("pk")
);
CREATE TABLE IF NOT EXISTS "topping" (
	"pk"	INTEGER,
	"id"	TEXT,
	"type"	TEXT,
	"jsonLevel"	TEXT,
	PRIMARY KEY("pk")
);
```

And then the tuples inserted will be:
```sql
INSERT INTO "files" ("pk","jsonLevel","filename") VALUES (1,'root','example.json');
INSERT INTO "donut" ("pk","id","name","ppu","jsonLevel") VALUES (1,'0001','Cake','0.55','root.donut');
INSERT INTO "donut" ("pk","id","name","ppu","jsonLevel") VALUES (2,'0002','Raised','0.55','root.donut');
INSERT INTO "donut" ("pk","id","name","ppu","jsonLevel") VALUES (3,'0003','Old Fashioned','0.55','root.donut');
INSERT INTO "batters.batter" ("pk","id","type","jsonLevel") VALUES (1,'1001','Regular','root.donut.batters.batter');
INSERT INTO "batters.batter" ("pk","id","type","jsonLevel") VALUES (2,'1002','Chocolate','root.donut.batters.batter');
INSERT INTO "batters.batter" ("pk","id","type","jsonLevel") VALUES (3,'1003','Blueberry','root.donut.batters.batter');
INSERT INTO "batters.batter" ("pk","id","type","jsonLevel") VALUES (4,'1004','Devil''s Food','root.donut.batters.batter');
INSERT INTO "topping" ("pk","id","type","jsonLevel") VALUES (1,'5001','None','root.donut.topping');
INSERT INTO "topping" ("pk","id","type","jsonLevel") VALUES (2,'5002','Glazed','root.donut.topping');
INSERT INTO "topping" ("pk","id","type","jsonLevel") VALUES (3,'5005','Sugar','root.donut.topping');
INSERT INTO "topping" ("pk","id","type","jsonLevel") VALUES (4,'5007','Powdered Sugar','root.donut.topping');
INSERT INTO "topping" ("pk","id","type","jsonLevel") VALUES (5,'5006','Chocolate with Sprinkles','root.donut.topping');
INSERT INTO "topping" ("pk","id","type","jsonLevel") VALUES (6,'5003','Chocolate','root.donut.topping');
INSERT INTO "topping" ("pk","id","type","jsonLevel") VALUES (7,'5004','Maple','root.donut.topping');
INSERT INTO "path_table" ("pk","pathId","pathLevel","lastPkLevel","nodeId","nodeTable","parentId","parentTable") VALUES (1,1,1,27,1,'files','','');
INSERT INTO "path_table" ("pk","pathId","pathLevel","lastPkLevel","nodeId","nodeTable","parentId","parentTable") VALUES (2,1,2,13,1,'donut',1,'files');
INSERT INTO "path_table" ("pk","pathId","pathLevel","lastPkLevel","nodeId","nodeTable","parentId","parentTable") VALUES (3,1,3,3,1,'batters.batter',1,'donut');
INSERT INTO "path_table" ("pk","pathId","pathLevel","lastPkLevel","nodeId","nodeTable","parentId","parentTable") VALUES (4,1,3,4,2,'batters.batter',1,'donut');
INSERT INTO "path_table" ("pk","pathId","pathLevel","lastPkLevel","nodeId","nodeTable","parentId","parentTable") VALUES (5,1,3,5,3,'batters.batter',1,'donut');
INSERT INTO "path_table" ("pk","pathId","pathLevel","lastPkLevel","nodeId","nodeTable","parentId","parentTable") VALUES (6,1,3,6,4,'batters.batter',1,'donut');
INSERT INTO "path_table" ("pk","pathId","pathLevel","lastPkLevel","nodeId","nodeTable","parentId","parentTable") VALUES (7,1,3,7,1,'topping',1,'donut');
INSERT INTO "path_table" ("pk","pathId","pathLevel","lastPkLevel","nodeId","nodeTable","parentId","parentTable") VALUES (8,1,3,8,2,'topping',1,'donut');
INSERT INTO "path_table" ("pk","pathId","pathLevel","lastPkLevel","nodeId","nodeTable","parentId","parentTable") VALUES (9,1,3,9,3,'topping',1,'donut');
INSERT INTO "path_table" ("pk","pathId","pathLevel","lastPkLevel","nodeId","nodeTable","parentId","parentTable") VALUES (10,1,3,10,4,'topping',1,'donut');
INSERT INTO "path_table" ("pk","pathId","pathLevel","lastPkLevel","nodeId","nodeTable","parentId","parentTable") VALUES (11,1,3,11,5,'topping',1,'donut');
INSERT INTO "path_table" ("pk","pathId","pathLevel","lastPkLevel","nodeId","nodeTable","parentId","parentTable") VALUES (12,1,3,12,6,'topping',1,'donut');
INSERT INTO "path_table" ("pk","pathId","pathLevel","lastPkLevel","nodeId","nodeTable","parentId","parentTable") VALUES (13,1,3,13,7,'topping',1,'donut');
INSERT INTO "path_table" ("pk","pathId","pathLevel","lastPkLevel","nodeId","nodeTable","parentId","parentTable") VALUES (14,1,2,20,2,'donut',1,'files');
INSERT INTO "path_table" ("pk","pathId","pathLevel","lastPkLevel","nodeId","nodeTable","parentId","parentTable") VALUES (15,1,3,15,1,'batters.batter',2,'donut');
INSERT INTO "path_table" ("pk","pathId","pathLevel","lastPkLevel","nodeId","nodeTable","parentId","parentTable") VALUES (16,1,3,16,1,'topping',2,'donut');
INSERT INTO "path_table" ("pk","pathId","pathLevel","lastPkLevel","nodeId","nodeTable","parentId","parentTable") VALUES (17,1,3,17,2,'topping',2,'donut');
INSERT INTO "path_table" ("pk","pathId","pathLevel","lastPkLevel","nodeId","nodeTable","parentId","parentTable") VALUES (18,1,3,18,3,'topping',2,'donut');
INSERT INTO "path_table" ("pk","pathId","pathLevel","lastPkLevel","nodeId","nodeTable","parentId","parentTable") VALUES (19,1,3,19,6,'topping',2,'donut');
INSERT INTO "path_table" ("pk","pathId","pathLevel","lastPkLevel","nodeId","nodeTable","parentId","parentTable") VALUES (20,1,3,20,7,'topping',2,'donut');
INSERT INTO "path_table" ("pk","pathId","pathLevel","lastPkLevel","nodeId","nodeTable","parentId","parentTable") VALUES (21,1,2,27,3,'donut',1,'files');
INSERT INTO "path_table" ("pk","pathId","pathLevel","lastPkLevel","nodeId","nodeTable","parentId","parentTable") VALUES (22,1,3,22,1,'batters.batter',3,'donut');
INSERT INTO "path_table" ("pk","pathId","pathLevel","lastPkLevel","nodeId","nodeTable","parentId","parentTable") VALUES (23,1,3,23,2,'batters.batter',3,'donut');
INSERT INTO "path_table" ("pk","pathId","pathLevel","lastPkLevel","nodeId","nodeTable","parentId","parentTable") VALUES (24,1,3,24,1,'topping',3,'donut');
INSERT INTO "path_table" ("pk","pathId","pathLevel","lastPkLevel","nodeId","nodeTable","parentId","parentTable") VALUES (25,1,3,25,2,'topping',3,'donut');
INSERT INTO "path_table" ("pk","pathId","pathLevel","lastPkLevel","nodeId","nodeTable","parentId","parentTable") VALUES (26,1,3,26,6,'topping',3,'donut');
INSERT INTO "path_table" ("pk","pathId","pathLevel","lastPkLevel","nodeId","nodeTable","parentId","parentTable") VALUES (27,1,3,27,7,'topping',3,'donut');
```
# Features
- Minimal number of tables: table batters.batter is named because the tool find no info in bundles but just 1 entity so there is no meaning in creating batters + batter tables 
- No repeated tuples: Glazed topping is imported one and isthen linked to the donuts Ids into path_table
- Json info: the level of each tuple is shown in the path table toghether with the last sub level so that knowing the row in path table one can find all the subtree
