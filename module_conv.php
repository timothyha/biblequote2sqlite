<?

// CONVERTER of modules from plain text format to sqlite db

/****

2012-04-21 version 0 
- we just load bibleqt.ini into "info" table

2013-09-01 version 1 
- we now add "books" table with OSIS codes and chapter titles
- "references" table will contain footnotes and links 
    Examples: 1. В этом мире <a href="reference_id">[1]</a> or 2. В этом <a href="reference_id">мире</a>

*************************************

module_conv.php

algorithm

1) receive folder name and database name
2) list all files in folder, work with bibleqt.ini regardless of the case
3) save text filenames "as is" in the table "info"

****/

function debug($msg) {
    echo date("H:i:s ").$msg."\n";
}

function sqlstr($s) {
    return str_replace("'", "''", $s);
}

// we cut a long string by pieces with length not greater than len
// and return an array
function splitstr($s, $len) {
    if(mb_strlen($s, "utf-8") <= $len) return array($s);
    
    debug("Splitting: ".substr($s,0,40)."...");
    
    $retval = array();
    $words = explode(" ", $s);
    $curstr = $words[0];
    for($i=1; $i<count($words); $i++) {
        if(mb_strlen($curstr." ".$words[$i], "utf-8") <= $len) $curstr .= " ".$words[$i];
        else {
            $retval[] = $curstr;
            $curstr = " ".$words[$i];
        }
    }
    if($curstr!=="") $retval[] = $curstr;
    
    return $retval;
}

/*** test of splitstr
echo strlen("Православный"); -- is 24, use mb_strlen
$s = "Православный автопробег В субботу вечером, накануне воскресного молебна, в Москве пройдет автомобильный флешмоб в поддержку РПЦ и патриарха Московского и всея Руси Кирилла. Организаторы мероприятия считают, что против предстоятеля Русской православной церкви сейчас ведется информационная кампания, и намерены своей акцией выразить ему поддержку.";
$a = splitstr($s, 80);
for($i=0;$i<count($a);$i++) $a[$i] = "\"".$a[$i]."\"";
print_r($a);
die();
***/

function convert_folder_to_module($directory, $database, $charset) {

    $osis66 = array("", "Gen", "Exod", "Lev", "Num", "Deut", "Josh", "Judg", "Ruth", "1Sam", "2Sam", "1Kgs", "2Kgs", "1Chr", "2Chr", "Ezra", "Neh", "Esth", "Job", "Ps", "Prov", "Eccl", "Song", "Isa", "Jer", "Lam", "Ezek", "Dan", "Hos", "Joel", "Amos", "Obad", "Jonah", "Mic", "Nah", "Hab", "Zeph", "Hag", "Zech", "Mal", "Matt", "Mark", "Luke", "John", "Acts", "Rom", "1Cor", "2Cor", "Gal", "Eph", "Phil", "Col", "1Thess", "2Thess", "1Tim", "2Tim", "Titus", "Phlm", "Heb", "Jas", "1Pet", "2Pet", "1John", "2John", "3John", "Jude", "Rev");

    $osis66_rus = array("", "Gen", "Exod", "Lev", "Num", "Deut", "Josh", "Judg", "Ruth", "1Sam", "2Sam", "1Kgs", "2Kgs", "1Chr", "2Chr", "Ezra", "Neh", "Esth", "Job", "Ps", "Prov", "Eccl", "Song", "Isa", "Jer", "Lam", "Ezek", "Dan", "Hos", "Joel", "Amos", "Obad", "Jonah", "Mic", "Nah", "Hab", "Zeph", "Hag", "Zech", "Mal", "Matt", "Mark", "Luke", "John", "Acts", "Jas", "1Pet", "2Pet", "1John", "2John", "3John", "Jude", "Rom", "1Cor", "2Cor", "Gal", "Eph", "Phil", "Col", "1Thess", "2Thess", "1Tim", "2Tim", "Titus", "Phlm", "Heb", "Rev");

    $osis77_rus = array("", "1Macc", "2Macc", "3Macc", "Bar", "1Esd", "2Esd", "Jdt", "EpJer", "Wis", "Sir", "Tob");

    $d = dir($directory);
    $files = array();
    while($de = $d->read()) {
        if($de=="." || $de=="..") continue;
        $files[] = $de;    
    }

    $is_module = 0;
    for($i=0; $i<count($files); $i++) {
        if(strtolower($files[$i])==="bibleqt.ini") {
            $is_module = 1;
            break;
        }
    }

    if($is_module)
        debug("This is a module.");
    else
        return("Sorry, ".$directory." does not contain a BibleQuote module.\n");

    $config_lines = file($directory."/".$files[$i]);
    debug(count($config_lines)." lines in configuration file.");
    
    // when you find PathName - please switch book counter on, and save all those keys with "num" = current value of book counter
    
    $booknum = 0;
    $config = array();
    $module = array(); // save module properties here
    $books = array(); // save book properties here
    
    for($i=0; $i<count($config_lines); $i++) {
        $line = $config_lines[$i];
        // remove empty lines and comments
        if(trim($line)==="") continue;
        if(substr($line,0,2)==="//") continue;
        if(substr($line,0,1)===";") continue;
                
        list($key,$value)=explode("=", $line);
        $key = trim($key);
        $value = trim($value);
        $value = iconv($charset,"utf-8",$value); // conversion happens here - do we need to check the list of available charsets???
        
        if($key==="PathName") $booknum++;
        
        $config[] = array($key,$value,$booknum);
        if($booknum==0) $module["$key"] = $value; 
        else $books[$booknum]["$key"] = $value;
    }
    print_r($module);
    //print_r($books);
    
    // READY FOR DATABASE

    $sql[] = "CREATE TABLE binary ( serial integer PRIMARY KEY AUTOINCREMENT NOT NULL, filename varchar(256) DEFAULT(NULL), fileref varchar(256), num integer DEFAULT(0), filedata blob DEFAULT(NULL) );";
    $sql[] = "CREATE TABLE books ( serial integer PRIMARY KEY AUTOINCREMENT NOT NULL, num integer, bookid char(16), chapters integer, fullname char(64), shortnames char(256) );";
    $sql[] = "CREATE TABLE chapters ( serial integer PRIMARY KEY AUTOINCREMENT NOT NULL, book integer, chapter integer, title char(256) );";
    $sql[] = "CREATE INDEX chaptersbook ON chapters (book);";
    $sql[] = "CREATE TABLE contents ( serial integer PRIMARY KEY AUTOINCREMENT NOT NULL, book integer, chapter integer, verse integer, txt text(4096) DEFAULT(NULL), num integer DEFAULT(0) );";
    $sql[] = "CREATE INDEX contentsbook ON contents (book);";
    $sql[] = "CREATE INDEX contentschapter ON contents (chapter);";
    $sql[] = "CREATE INDEX contentsverse ON contents (verse);";
    $sql[] = "CREATE TABLE info ( serial integer PRIMARY KEY AUTOINCREMENT, \"key\" varchar(64) DEFAULT(NULL), value varchar(1024) DEFAULT(NULL), num integer DEFAULT(0) );";

    //debug("SQLite version is: ".sqlite_libversion());   
  
    $sql[] = "update sqlite_sequence set seq = 0 where name <> ''";
    $sql[] = "delete from info where key <> ''";
    $sql[] = "delete from contents where book <> ''";
    
    $sql[] = "insert into info(key, value) values('ModuleFormatVersion', '1.0');";

    // INFO
    for($i=0;$i<count($config);$i++)
    $sql[] = "insert into info(key,value,num) values('".sqlstr($config[$i][0])."', '".sqlstr($config[$i][1])."', '".$config[$i][2]."')";

/**

TODO: OSIS code setting works only for full Bible modules.  For others we will need to fix the code manually or FIND AN ALGORITHM

**/

    // BOOKS

    for($b=1;$b<=$module['BookQty'];$b++) {

        if($module['Bible']=="Y") {
            if($books[45]['ChapterQty']==5) // RUSSIAN order of modules, James has 5 chapters
                $osis = (($b<=66) ? $osis66_rus[$b] : $osis77_rus[$b-66]);
            else
                $osis = (($b<=66) ? $osis66[$b] : $osis77_rus[$b-66]);
        }
        else    $osis = $books[$b]['BookId'];

        $sql[] = "insert into books(num,bookid,chapters,fullname,shortnames) values('".$b."', '".$osis
            ."', '".$books[$b]['ChapterQty']."', '".$books[$b]['FullName']."', '".$books[$b]['ShortName']."');";
    }

    // CHAPTERS
    for($b=1;$b<=$module['BookQty'];$b++) {
        for($c=1;$c<=$books[$b]['ChapterQty'];$c++)
        $sql[] = "insert into chapters(book,chapter,title) values('".$b."', '".$c."', '".$books[$b]['FullName']." ".$c."');";
    }
    
    // CONTENTS
    for($b=1;$b<=$module['BookQty'];$b++) {
        $booktext = file_get_contents($directory."/".$books[$b]['PathName']);
        $booktext = iconv($charset, "utf-8", $booktext);
        $chapters = explode($module['ChapterSign'], $booktext);                    
        debug("Book #".$b." chapterqty: ".(count($chapters)-1));
        
        // insert chapter 0 -- the part before first ChapterSign
        $text = sqlstr($chapters[0]);
        $textarr = splitstr($text,4096);
        for($i=0;$i<count($textarr);$i++)
        $sql[] = "insert into contents(book,chapter,verse,txt,num) values (0,0,0,'".$textarr[$i]."',".$i.")";
        
        // for other chapters, ChapterSign will be part of the first verse/paragraph of the chapter
        for($c=1;$c<count($chapters);$c++) {
            $verses = explode($module['VerseSign'], $chapters[$c]);
            
            // insert verse 0 -- the part before first VerseSign
            $text = sqlstr($module['ChapterSign'].$verses[0]);
            $textarr = splitstr($text,4096);
            for($i=0;$i<count($textarr);$i++)
            $sql[] = "insert into contents(book,chapter,verse,txt,num) values (".$b.",".$c.",0,'".$textarr[$i]."',".$i.")";
            
            // insert other verses
            for($v=1;$v<count($verses);$v++) {
                $text = sqlstr($verses[$v]);
                $textarr = splitstr($text,4096);
                for($i=0;$i<count($textarr);$i++)
                $sql[] = "insert into contents(book,chapter,verse,txt,num) values (".$b.",".$c.",".$v.",'".$textarr[$i]."',".$i.")";            
            }
        }        
    }
    
    $fp = fopen("temp_sqlite_commands.sql", "w");
    for($i=0;$i<count($sql);$i++)
    fputs($fp, $sql[$i].";\n");
    fclose($fp);
    
    debug("Executing sqlite3...");
    $database = basename($database);
    unlink($database);

    //copy("module_sample1.sqlite", $database);
    //die();

    // in Windows we will output $database.sql file

    if(strtoupper(substr(PHP_OS,0,3))=="WIN") {
        rename("temp_sqlite_commands.sql", $database.".sql");
        debug("For Windows we create ".$database.".sql. Please execute the SQL file for database ".$database);
    }
    else {
        exec("cat temp_sqlite_commands.sql | sqlite3 ".escapeshellarg($database));
        unlink("temp_sqlite_commands.sql");
    }
    debug("Completed.");
}

date_default_timezone_set("Europe/Moscow");

if($_SERVER['argc']<4) 
die("Not enough command-line parameters.\nUsage: php module_conv.php MODULE_FOLDER_NAME SQLITE_DB_NAME CHAR_SET\nExample: php module_conv.php RstStrong bible_rst.sqlite windows-1251\n");

$result = convert_folder_to_module($_SERVER['argv'][1],$_SERVER['argv'][2],$_SERVER['argv'][3]);

if($result!=="OK") die($result);


