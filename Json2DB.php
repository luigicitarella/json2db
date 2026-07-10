<?php

function inArray($result, $value) {
    $return = false;
    foreach ($result as $resultValue) {
        $return = $return || $resultValue['name'] == $value;
    }
    return $return;
}



function createTablePath($pdo) {
    $create_table_sql = 'CREATE TABLE IF NOT EXISTS path_table (
    pk INTEGER PRIMARY KEY ASC,
    "pathId"    INTEGER,
	"pathLevel" INTEGER,
	"lastPkLevel" INTEGER,
    "nodeId"	INTEGER,
	"nodeTable"	TEXT,
    "parentId"	INTEGER,
	"parentTable"	TEXT
        
)';
    $pdo->exec($create_table_sql);
    return null;
}

function saveRow($pdo, $table, $row, $pathId, $pathLevel, $parentPK, $parentTable) {
    
    $pk = '';
    if (!is_array($row)) {
        $rowToInsert['value']=$row;
    }else {
        $rowToInsert = array_filter($row, function ($item) {
            return !is_array($item);
        });
    }
    
    $existrow = false;
    
    $tableSQL = '"'.$table.'"';
    $fields = $pdo->query("PRAGMA table_info($tableSQL)")->fetchAll();
    
    if (count($fields)==0) {
        $create_table_sql = 'CREATE TABLE IF NOT EXISTS '.$tableSQL.' (pk INTEGER PRIMARY KEY ASC)';
        $pdo->exec($create_table_sql);
    }
    $result = $pdo->query("SELECT case when max(pk) is null then 0 else pk end pk FROM $tableSQL")->fetchAll();
    $pk = $result[0]['pk']+1;
    
    
    
     if (count(array_intersect(array_keys($rowToInsert), array_column($fields, 'name'))) === count(array_keys($rowToInsert))) {
        
        $copyrow = $rowToInsert;
        $copyrow['pk'] = '';
        
        $remainingFields = array_diff(array_column($fields, 'name'),array_keys($copyrow));
        
        
        
        $combined = array_map(function($a, $b) { return '"'.$a.'"=' . "'" . $b."'"; }, array_keys($rowToInsert),  preg_replace("/(?*')/","'", array_values($rowToInsert)));
        foreach ($remainingFields as $remainingField) {
            $combined[]='"'.$remainingField.'" is null';
        }
        
        if (count($combined)>0) {
            $check_exist = $pdo->query('select pk, count(*) as c from '.$tableSQL.' where '.implode(' and ', $combined))->fetchAll();
            $existrow = $check_exist[0]['c']>0;
        }
    }
    
    if (!$existrow) {
        if (count(array_intersect(array_keys($rowToInsert), array_column($fields, 'name'))) === count(array_keys($rowToInsert))) {
            if (count(array_keys($rowToInsert))>0) {
                $query = 'INSERT INTO '.$tableSQL.' ("'.implode('","', array_keys($rowToInsert)).'") VALUES('."'".implode("','",preg_replace("/(?*')/","'", array_values($rowToInsert)))."')";
                
            }else {
                $query = 'INSERT INTO '.$tableSQL.' DEFAULT VALUES';
            }
            $pdo->exec($query);
            
        }else {
            $first = true;
            foreach ($rowToInsert as $key => $value) {
                $keyToSql = '"'.$key.'"';
                if (!inArray($fields, $key)) {
                    $create_field_sql = "ALTER TABLE $tableSQL ADD $keyToSql TEXT";
                    $pdo->exec($create_field_sql);
                }
                
                if ($first) {
                    $query = "INSERT INTO $tableSQL ($keyToSql) VALUES('".preg_replace("/(?*')/","'", $value)."')";
                    //$query = "INSERT INTO $tableSQL ($keyToSql) VALUES(".$pdo->quote($value).")";
                    $first = false;
                }else {
                    
                    $query = "UPDATE $tableSQL SET $keyToSql = '".preg_replace("/(?*')/","'", $value)."' WHERE pk = ".$pk;
                    //            $query = "UPDATE $tableSQL SET $keyToSql = ".$pdo->quote($value)." WHERE pk = $currentPK";
                }
                
                $pdo->exec($query);
            }
        }
    }else {
        $pk=$check_exist[0]['pk'];
    }
    
    $rowToInsert_link["pathId"]=$pathId;
    $rowToInsert_link["pathLevel"]=$pathLevel;
    $rowToInsert_link["nodeId"]=$pk;
    $rowToInsert_link["nodeTable"]=$table;
    $rowToInsert_link["parentId"]=$parentPK;
    $rowToInsert_link["parentTable"]=$parentTable;
    
    $query = 'Insert into path_table ("'.implode('","', array_keys($rowToInsert_link)).'") VALUES ('."'".implode("','",preg_replace("/(?*')/","'", array_values($rowToInsert_link)))."')";
    $pdo->exec($query);
    
    $result = $pdo->query("SELECT max(pk) pk FROM path_table")->fetchAll();
    $pkLastPathTable = $result[0]['pk'];
    
    
    
    
    return [$pk, $pkLastPathTable];
    
    
}

function update_link_table_adding_last_row($pdo, $rowToUpdate) {
    $result = $pdo->query("SELECT case when max(pk) is null then 0 else pk end pk FROM path_table")->fetchAll();
    $pk = $result[0]['pk'];
    
    $query = "update path_table set lastPkLevel = " .$pk. " where pk = ".$rowToUpdate;
    $exitValue = $pdo->exec($query);
}

function duplicatePathTable($pdo, $pathId, $pathLevel) {
    $result = $pdo->query("SELECT * FROM path_table where pathId = ".$pathId ." and pathLevel<=".$pathLevel)->fetchALL(PDO::FETCH_ASSOC);
    foreach ($result as $entry) {
        $entry['pathId']++;
        $query = 'Insert into path_table ("'.implode('","', array_keys($entry)).'") VALUES ('."'".implode("','",array_values($entry))."')";
        $pdo->exec($query);
    }
}




function navigatejsonPath(&$nodes,$pdo, $table, &$pathId, $pathLevel, $parentPK='', $parentTable='')
{
    
    if (is_array($nodes) && array_is_list($nodes)) {
        if (count($nodes) > 0) {
            foreach ($nodes as &$node) {
                navigatejsonPath($node,$pdo, $table, $pathId, $pathLevel, $parentPK, $parentTable);
            }
        }
    }else {
        [$pk, $pkLastPathTable] = saveRow($pdo, $table, $nodes, $pathId, $pathLevel, $parentPK, $parentTable);
        
        if (is_array($nodes)) {
            foreach ($nodes as $key => &$node) {
                if (is_array($node) && count($node)>0) {
                    navigatejsonPath($node,$pdo, $key, $pathId, $pathLevel+1, $pk, $table);
                }
                
            }
        }
        update_link_table_adding_last_row($pdo, $pkLastPathTable);
    }
    return null;
    
}

function import_array_to_sqlite(&$pdo, $array, $options = array())
{
    extract($options);
    
    $create_table_sql = "CREATE TABLE IF NOT EXISTS ";
    $pdo->exec($create_table_sql);
    
    $insert_sql = "INSERT INTO $table ($insert_fields_str) VALUES ($insert_values_str)";
    $insert_sth = $pdo->prepare($insert_sql);
    $insert_sth->execute($insert_sql);
    
    return ;
    
}

function array_flatten($array, $prefix = '', $iterate = true, $removeNull = false)
{
    $flat = array();
    $sep = ".";
    
    if (!is_array($array)) $array = (array)$array;
    
    foreach($array as $key => $value)
    {
        $_key = ltrim($prefix.$sep.$key, ".");
        
        if (is_array($value) || is_object($value))
        {
            if (is_object($value) or count($value)>0 or $removeNull) {
                if ($iterate) {
                    $flat = array_merge($flat, array_flatten($value, $_key, $iterate, $removeNull));
                }
            }
            else {
                $flat[$_key] = null;
            }
        }
        else
        {
            if (!($removeNull and (is_null($value) or $value == ''))) {
                $flat[$_key] = $value;
            }
        }
    }
    
    return $flat;
}

function check_strings_in_array($arr)
{
    // Use array_map to check if each element is a string, then sum the results
    // If the sum is equal to the total count of elements, it means all elements are strings
    return array_sum(array_map('is_string', $arr)) == count($arr);
}

function array_level(&$array, $prefix = '', $removeDigits = true, $lookup = null)
{

    $sep = ".";
    
    if (!is_array($array)) $array = (array)$array;
    if (!array_is_list($array))  {
        $array['jsonLevel'] = $prefix;
    }
    
    foreach($array as $key => &$value)
    {
        if ( is_array($value) || is_object($value) )
        {
            $keyArray = explode('.',$prefix);
            $matchString = end($keyArray);
            $ret='';
            if (!is_null($lookup)) {
                if (array_key_exists($matchString, $lookup)) {
                    $ret = $lookup[$matchString];
                }
            }
            $add2key='';
            if ($ret<>'' and !array_is_list($value)) {
                if (array_key_exists($ret,$value)) {
                    $add2key = '('.$ret.'='.$value[$ret].')';
                }
            }
            
            if ($removeDigits and array_is_list($array)) {
                $_key = ltrim($prefix.$add2key, ".");
            }
            else {
                $_key = ltrim($prefix.$sep.$key.$add2key, ".");
            }
            
            if (array_is_list($value))  {
                if (check_strings_in_array($value) AND count($value)>0)
                {
                    foreach ($value as &$valueIn) {
                        $valueInBackup['value'] = $valueIn;
                        $valueInBackup['jsonLevel'] = $prefix;
                        $valueIn = (array)$valueInBackup;
                    };
                }
            }
            array_level($value, $_key, $removeDigits, $lookup);
        }
        
    }
    

}

function array_set( &$array, $key, $value )
{
    if ( is_null( $key ) )
        return $array = $value;
        
        $keys = explode( '.', $key );
        
        while ( count( $keys ) > 1 )
        {
            $key = array_shift( $keys );
            
            // If the key doesn't exist at this depth, we will just create an empty array
            // to hold the next value, allowing us to create the arrays to hold final
            // values at the correct depth. Then we'll keep digging into the array.
            if ( ! isset( $array[$key] ) || ! is_array( $array[$key] ) )
            {
                $array[$key] = array();
            }
            
            $array =& $array[$key];
        }
        
        $array[array_shift( $keys )] = $value;
        
        return $array;
}

function array_unflatten( $collection )
{
    $collection = (array) $collection;
    
    $output = array();
    
    foreach ( $collection as $key => $value )
    {
        array_set( $output, $key, $value );
        
        if ( is_array( $value ) && ! strpos( $key, '.' ) )
        {
            $nested = array_unflatten( $value );
            
            $output[$key] = $nested;
        }
    }
    
    return $output;
}

function filterFlattenArray($arrayFlattened, $regexFilterArray)
{
    
    if (!is_array($arrayFlattened)) $arrayFlattened = (array)$arrayFlattened;
    
    $filtered = $arrayFlattened;
    
    foreach($arrayFlattened as $key => $value)
    {
        $found = false;
        $regexFilterArrayLen = count($regexFilterArray);
        $i=0;
        while (!$found and $i<$regexFilterArrayLen) {
            $found = preg_match($regexFilterArray[$i], $key);
            $i++;
        }
        if (!$found) {
            unset($filtered[$key]);
        }
        
    }
    
    return $filtered;
}

function filterArray($array, $regexFilterArray)
{
    
    $arrayFlattened = array_flatten($array);
    $filteredFlattened = filterFlattenArray($arrayFlattened, $regexFilterArray);
    $filteredArray = array_unflatten($filteredFlattened);
    return $filteredArray;
}


function array_shrink($data,$prefix='',$sep ='.') {
    
    $outArray =[];
    
    if (is_array($data)) {
        
        foreach ($data as $key => $value) {
            $_key = ltrim($prefix.$sep.$key, ".");
            if (!is_array($value)) {
                $outArray[$_key] = $value;
            }
            else {
                if (array_is_list($value)) {
                    if (count($value) == count(array_filter($value, 'is_array'))) {
                        if (count($value)>0) {
                            for ($i = 0; $i < count($value); $i++) {
                                $outArray[$_key][] = array_shrink($value[$i]);
                            }
                        }else {
                            $outArray[$_key] = null;
                        }
                    }else {
                        $outArray[$_key] = implode(';',$value);
                    }
                    
                }
                else {
                    $outArray  = array_merge($outArray, array_shrink($value, $_key, $sep));
                }
            }
        }
    }
    else {
        $outArray['value'] = $data;
    }
    return $outArray;
}


function reduce_data (&$data) {
    
    if (is_array($data)) {
        
        foreach ($data as $key => &$value) {
        }
    }
}


function Json2DB($JsonDir, $destDB, $appendToDB = false, $regexFilterArray = null, $lookup = null) {
    /*
    $lookup = [
        
        'node' => 'name',
        'othernode' => 'name',
        'morenode'  => 'more',
    ];
    $regexFilterArray =
    [
        '/^root.[0-9]+.name$/',
    ]
    */

    if (!$appendToDB && file_exists($destDB)) {
        unlink($destDB);
    }
    $PDO = new PDO('sqlite:'.$destDB);
    //$PDO = new PDO('sqlite::memory:');
    
    createTablePath($PDO);
    $pathId=1;
    
    
    $Directory = new RecursiveDirectoryIterator($JsonDir);
    $Iterator = new RecursiveIteratorIterator($Directory);
    $filteredFiles =  new RegexIterator($Iterator, '/^.+\.json$/i', RecursiveRegexIterator::GET_MATCH);
    
    $PDO->beginTransaction();
    foreach ($filteredFiles as $files) {
        foreach ($files as $file) {
            
            $pathLevel = 1;
            $str = file_get_contents($file);
            $data = json_decode($str, true); // decode the JSON into an associative array

            $data = array_shrink($data);
            reduce_data($data);
            
            
            array_level($data,'root',true, $lookup);
            
            if (!is_null($regexFilterArray)) {
                $data = filterArray($data, $regexFilterArray);
            }
            $data['filename'] = basename($file);
            navigatejsonPath($data,$PDO, 'files',$pathId,$pathLevel);
            $pathId++;
            

        }
        echo basename($file)."\r\n";
    }
    $PDO->commit();
    
}

    
    
