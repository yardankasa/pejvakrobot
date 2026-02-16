<?php
include('config.php');

$settings = $pdo->query("SELECT * FROM panel WHERE id = '85' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$settings) {
    $settings = ['time' => 0, 'time_vip' => 0];
}
$settings['time'] = $settings['time'] ?? 0;
$settings['time_vip'] = $settings['time_vip'] ?? 0;

if(time() >= $settings['time']){
    $id = [];
    $query = $pdo->query("SELECT * FROM files")->fetchAll();
    foreach($query as $q){
        if($q['ads_type']=='free')
            $id[] = $q['id'];
    }
    if(!empty($id)){
        $timee = time() + 43200;
        $source = $id[array_rand($id)];
        $pdo->exec("UPDATE panel SET time = '$timee' WHERE id = '85' LIMIT 1");
        $pdo->exec("UPDATE send_all SET type = 'source', count = '0', msg_id = '$source' WHERE id = '85' LIMIT 1");
    }
}

if(time() >= $settings['time_vip']){
    $id_vip = [];
    $query = $pdo->query("SELECT * FROM files")->fetchAll();
    foreach($query as $q){
        if($q['ads_type']=='vip')
            $id_vip[] = $q['id'];
    }
    if(!empty($id_vip)){
        $timee2 = time() + 172800;
        $source = $id_vip[array_rand($id_vip)];
        $pdo->exec("UPDATE panel SET time_vip = '$timee2' WHERE id = '85' LIMIT 1");
        $pdo->exec("UPDATE send_all SET type = 'source_vip', count = '0', msg_id = '$source' WHERE id = '86' LIMIT 1");
    }
}