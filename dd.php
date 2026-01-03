<?php
include('config.php');

$settings = $pdo->query("SELECT * FROM panel WHERE id = '85' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if(time() >= $settings['time']){
    $query = $pdo->query("SELECT * FROM files")->fetchAll();
    foreach($query as $q){
        if($q['ads_type']=='free')
            $id[] = $q['id'];
    }
    $timee = time() + 43200;
    $source = $id[array_rand($id)];
       echo $source;
    $pdo->exec("UPDATE panel SET time = '$timee' WHERE id = '85' LIMIT 1");
    $pdo->exec("UPDATE send_all SET type = 'source', count = '0', msg_id = '$source' WHERE id = '85' LIMIT 1");
}

if(time() >= $settings['time_vip']){
    $query = $pdo->query("SELECT * FROM files")->fetchAll();
    foreach($query as $q){
        if($q['ads_type']=='vip')
            $id[] = $q['id'];
    }
    $timee2 = time() + 172800;
    $source = $id[array_rand($id)];
       echo $source;
    $pdo->exec("UPDATE panel SET time_vip = '$timee2' WHERE id = '85' LIMIT 1");
    $pdo->exec("UPDATE send_all SET type = 'source_vip', count = '0', msg_id = '$source' WHERE id = '86' LIMIT 1");
}