<?php
  $method = $_SERVER['REQUEST_METHOD'];


  if ($_GET && $_GET['url']) {
    $headers = getallheaders();
    $headers_str = [];
    $url = $_GET['url'];
    
    foreach ( $headers as $key => $value){
      if($key == 'Host')
        continue;
      $headers_str[]=$key.":".$value;
    }

    $ch = curl_init($url);

    curl_setopt($ch,CURLOPT_URL, $url);
    if( $method !== 'GET') {
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    }

    if($method == "PUT" || $method == "PATCH" || ($method == "POST" && empty($_FILES))) {
      $data_str = file_get_contents('php://input');
      curl_setopt($ch, CURLOPT_POSTFIELDS, $data_str);
      //error_log($method.': '.$data_str.serialize($_POST).'\n',3, 'err.log');
    }
    elseif($method == "POST") {
      $data_str = array();
      if(!empty($_FILES)) {
        foreach ($_FILES as $key => $value) {
          $full_path = realpath( $_FILES[$key]['tmp_name']);
          $data_str[$key] = '@'.$full_path;
        }
      }
      //error_log($method.': '.serialize($data_str+$_POST).'\n',3, 'err.log');

      curl_setopt($ch, CURLOPT_POSTFIELDS, $data_str+$_POST);
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers_str );

    $result = curl_exec($ch);
    curl_close($ch);

    //header('Content-Type: application/json');
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Type: text/html; charset=utf-8');
    echo $result;
  }
  else {
    echo $method;
    var_dump($_POST);
    var_dump($_GET);
    $data_str = file_get_contents('php://input');
    echo $data_str;

  }
?>
