# json2db
PHP code to convert multiple heavly nested json files to sqlite db

It navigate inside json structure to find info that can be mapped to tables and import them into SQLITE whithout having to know about the DB schema.
The tool will also insert a column about the json level of the tuple and an unique id of the table.
The first tuple of each file will be stored in the files table with any root info.
If he finds a tuple that is already inserted it will not import them twice.
For this reason a parent child logic will not work. In order to reconstruct the links between the tables a path table is generatedthat is based on the sctucture of the imported json.


# Usage
Json2DB($JsonDir, $destDB, $appendToDB = false, $regexFilterArray = null, $lookup = null) 

$appendToDB can be set to true to append more files to the same db
$regexFilterArray can be used to filter jsons before creating the db
    
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
