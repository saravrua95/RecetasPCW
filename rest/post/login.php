<?php
// FICHERO: rest/post/login.php

$METODO = $_SERVER['REQUEST_METHOD'];
// EL METODO DEBE SER POST. SI NO LO ES, NO SE HACE NADA.
if($METODO<>'POST') exit();
// PETICIONES POST ADMITIDAS:
//   rest/login/

// =================================================================================
// =================================================================================
// INCLUSION DE LA CONEXION A LA BD
   require_once('../configbd.php');
// =================================================================================
// =================================================================================

// =================================================================================
// CONFIGURACION DE SALIDA JSON
// =================================================================================
header("Access-Control-Allow-Orgin: *");
header("Access-Control-Allow-Methods: *");
header("Content-Type: application/json");
// =================================================================================
// Se prepara la respuesta
// =================================================================================
$R = []; // Almacenará el resultado.
$RESPONSE_CODE = 200; // código de respuesta por defecto: 200 - OK
// =================================================================================
// =================================================================================
// Se supone que si llega aquí es porque todo ha ido bien y tenemos los datos correctos:
$PARAMS = $_POST;
// =================================================================================
// COMPROBACIÓN DE PARÁMETROS
if(!isset($PARAMS['login']) || !isset($PARAMS['pwd']))
{
  $rtn = array('RESULTADO' => 'ERROR', 'CODIGO' => '400', 'DESCRIPCION' => "Faltan parámetros en la petición.");
  http_response_code(400);
  print json_encode($rtn);
  exit();
}

// Se pillan los parámetros de la petición:
$usu = sanatize($PARAMS['login']);
$pwd = sanatize($PARAMS['pwd']);

try{
  // ******** INICIO DE TRANSACCION **********
  mysqli_query($link, "BEGIN");
  $mysql = "select * from usuario where login='" . $usu . "'";
  if( $res = mysqli_query( $link, $mysql ) )
  {
    $row = mysqli_fetch_assoc( $res ); // Se transforma en array el registro encontrado

    if( mysqli_num_rows($res)==1 && $row['pwd'] == $pwd ) // Se comprueba si el resultado tiene un único registro y si el password coincide
    {
      $tiempo = time(); // se toma la hora a la que se hizo el login
      $key    = md5( $pwd . date('YmdHis', $tiempo) );

      $mysql  = 'update usuario set clave="' . $key . '"';
      $mysql .= ', ultimo_acceso="' . date('Y-m-d H:i:s', $tiempo) . '"';
      $mysql .= ' where login="' . $usu . '"';

      if( mysqli_query( $link, $mysql ) )
      {
        $R['RESULTADO'] = 'OK';
        $R['CODIGO']    = 200;
        $R['clave']     = $key;
        $R['login']     = $usu;
        $R['nombre']    = $row['nombre'];
        $R['email']     = $row['email'];
        $R['fnac']      = $row['fnac'];
      }
      else
        $RESPONSE_CODE = 500;
    }
    else
        $RESPONSE_CODE = 401;
  }
  else
      $RESPONSE_CODE = 401;

  switch($RESPONSE_CODE)
  {
    case 401:
        $R = array('RESULTADO' => 'ERROR', 'CODIGO' => '401', 'DESCRIPCION' => 'Login/password no correcto');
      break;
    case 500:
        $R = array('RESULTADO' => 'ERROR', 'CODIGO' => '500', 'DESCRIPCION' => 'Se ha producido un error en el servidor.');
      break;
  }
  // ******** FIN DE TRANSACCION **********
  mysqli_query($link, "COMMIT");
} catch(Exception $e){
  // Se ha producido un error, se cancela la transacción.
  mysqli_query($link, "ROLLBACK");
}
// =================================================================================
// SE CIERRA LA CONEXION CON LA BD
// =================================================================================
mysqli_close($link);
// =================================================================================
// SE DEVUELVE EL RESULTADO DE LA CONSULTA
// =================================================================================
try {
    // Here: everything went ok. So before returning JSON, you can setup HTTP status code too
    http_response_code($RESPONSE_CODE);
    print json_encode($R);
}
catch (SomeException $ex) {
    $rtn = array('RESULTADO' => 'ERROR', 'CODIGO' => '500', 'DESCRIPCION' => "Se ha producido un error al devolver los datos.");
    http_response_code(500);
    print json_encode($rtn);
}
?>