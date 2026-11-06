<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../../lib/backup_package.php';
$dir = sys_get_temp_dir() . '/doyel-package-check-' . bin2hex(random_bytes(8));
mkdir($dir,0700);
$paths=[];
$save = static function(string $data) use ($dir,&$paths): string { $path=$dir.'/'.bin2hex(random_bytes(8)); file_put_contents($path,$data); chmod($path,0600); $paths[]=$path; return $path; };
try {
    $schema=doyel_schema_tables(); $tables=[]; $footerTables=[]; $lines=[];
    $special=['0',null,'বাংলা',"a\0b",'9007199254740993','0.125','1e100'];
    $first=array_keys($schema)[array_search(max(array_map(static fn($d)=>count($d['columns']),$schema)),array_values(array_map(static fn($d)=>count($d['columns']),$schema)),true)]; $values=array_fill(0,count($schema[$first]['columns']),'');
    foreach($values as $i=>$_) $values[$i]=$special[$i%count($special)];
    foreach($schema as $table=>$definition) {
        $tables[$table]=['columns'=>$definition['columns'],'count'=>$table===$first?'1':'0','next_id'=>'100'];
        $line=$table===$first?doyel_backup_data_encode(['kind'=>'row','table'=>$table,'values'=>$values])."\n":'';
        if($line!=='')$lines[]=$line;
        $footerTables[$table]=['count'=>$table===$first?1:0,'sha256'=>hash('sha256',$line)];
    }
    $header=['kind'=>'header','format'=>'doyel-data','format_version'=>1,'schema_version'=>1,'app'=>doyel_app_version(),'snapshot_utc'=>'2026-11-07T00:00:00Z','timezone'=>'+00:00','schema_fingerprint'=>doyel_schema_expected_fingerprint(),'tables'=>$tables];
    $body=doyel_backup_data_encode($header)."\n".implode('',$lines);
    $footer=['kind'=>'footer','tables'=>$footerTables,'payload_sha256'=>hash('sha256',$body),'inventory'=>['financial'=>[],'dates'=>[],'roles'=>[],'customer_balances'=>[]]];
    $plain=$body.doyel_backup_data_encode($footer)."\n";
    $gzip=gzencode($plain);
    $seen=[]; $valid=$save($gzip);
    doyel_backup_package_read($valid,static function($table,$row)use(&$seen){$seen[]=$row;});
    if($seen!==[$values])throw new RuntimeException('Exact field preservation failed');
    $corrupt=$gzip; $corrupt[strlen($corrupt)-8]=chr(ord($corrupt[strlen($corrupt)-8])^1);
    $bad=['crc'=>$corrupt,'unknown-kind'=>gzencode(str_replace('"kind":"row"','"kind":"sql"',$plain)),'truncated'=>substr($gzip,0,-5),'trailing'=>$gzip.'x','second-member'=>$gzip.$gzip,'missing-footer'=>gzencode($body),'duplicate-footer'=>gzencode($plain.doyel_backup_data_encode($footer)."\n"),'row-after-footer'=>gzencode($plain.$lines[0]),'checksum'=>gzencode(str_replace('1e100','1e101',$plain)),'wrong-count'=>gzencode(str_replace('"count":"1"','"count":"2"',$plain)),'invalid-utf8'=>gzencode($body."\xff\n"),'numeric-field'=>gzencode(str_replace('"values":["0"','"values":[0',$plain))];
    foreach($bad as $label=>$data){$rejected=false;try{doyel_backup_package_read($save($data));}catch(Throwable $error){$rejected=true;}if(!$rejected)throw new RuntimeException('Accepted '.$label);}
    foreach(['compressed_bytes'=>1,'uncompressed_bytes'=>1,'line_bytes'=>1] as $limit=>$value){$rejected=false;try{doyel_backup_package_read($valid,null,[$limit=>$value]);}catch(Throwable $error){$rejected=true;}if(!$rejected)throw new RuntimeException('Accepted limit '.$limit);}
    echo "PASS exact strings/NULL/Unicode/NUL/fractions/exponent + 12 malformed packages + 3 bounds\n";
} finally {foreach($paths as $path)unlink($path);rmdir($dir);}
