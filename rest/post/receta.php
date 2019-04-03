<?php
// FICHERO: rest/post/receta.php

$METODO = $_SERVER['REQUEST_METHOD'];
// EL METODO DEBE SER POST. SI NO LO ES, NO SE HACE NADA.
if($METODO<>'POST') exit();
// PETICIONES POST ADMITIDAS:
//   rest/receta/
//       Params: l:login;
//   rest/receta/{ID_RECETA}/ingredientes
//       Params: i:vector de ingredientes en formato JSON (['ingrediente 1','ingrediente 2', ...])
//   rest/receta/{ID_RECETA}/comentario
//       Params: l:login; titulo:titulo del comentario; texto:texto del comentario
//   rest/receta/{ID_RECETA}/voto/{VALOR_VOTO}?l={LOGIN}   -> {VALOR_VOTO} es 1 si el voto es positivo y 0 si negativo.

// =================================================================================
// =================================================================================
// INCLUSION DE LA CONEXION A LA BD
require_once('../configbd.php');
// =================================================================================
// =================================================================================

$RECURSO = explode("/", $_GET['prm']);
// =================================================================================
// CONFIGURACION DE SALIDA JSON Y CORS PARA PETICIONES AJAX
// =================================================================================
header("Access-Control-Allow-Orgin: *");
header("Access-Control-Allow-Methods: *");
header("Content-Type: application/json");
// =================================================================================
// Se pillan las cabeceras de la petición y se comprueba que está la de autorización
// =================================================================================
$headers = apache_request_headers();
if(!isset($headers['Authorization']))
{ // Acceso no autorizado
  $R = array('RESULTADO' => 'ERROR', 'CODIGO' => '401', 'DESCRIPCION' => 'Falta autorización');
  http_response_code(401); // 401 - Unauthorized access
  print json_encode($R);
  exit();
}

// =================================================================================
// Se prepara la respuesta
// =================================================================================
$R             = [];  // Almacenará el resultado.
$RESPONSE_CODE = 200; // código de respuesta por defecto: 200 - OK
// =================================================================================
// =================================================================================
// Se supone que si llega aquí es porque todo ha ido bien y tenemos los datos correctos
// de la nueva entrada, NO LAS FOTOS. Las fotos se suben por separado una vez se haya
// confirmado la creación correcta de la entrada.
$PARAMS      = $_POST;
$clave       = $headers['Authorization'];
$login       = sanatize($PARAMS['l']);

if(!isset($PARAMS['l']))
{
  $RESPONSE_CODE = 400;
  $R = array('RESULTADO' => 'ERROR', 'CODIGO' => $RESPONSE_CODE, 'DESCRIPCION' => 'Faltan datos en la petición');
  http_response_code($RESPONSE_CODE); // 400 - Bad request
  print json_encode($R);
  exit();
}

if( !comprobarSesion($login,$clave) )
{
  $RESPONSE_CODE = 401;
  $R = array('RESULTADO' => 'ERROR', 'CODIGO' => $RESPONSE_CODE, 'DESCRIPCION' => 'Login o clave incorrecto.');
}
else
{
    try{
        mysqli_query($link, "BEGIN");
        $ID = array_shift($RECURSO);
        if(!is_numeric($ID))
        { // Si no es numérico $ID es porque se está creando una receta
            $nombre      = sanatize($PARAMS['n']);
            $comensales  = sanatize($PARAMS['c']);
            $tiempo      = sanatize($PARAMS['t']);
            $dificultad  = sanatize($PARAMS['d']);
            $elaboracion = sanatize(nl2br($PARAMS['e'],false));
            // =================================================================================
          $mysql  = 'insert into receta(nombre,elaboracion,comensales,tiempo,dificultad,autor) ';
          $mysql .= 'values(';
          $mysql .= '"' . $nombre .'","' . $elaboracion . '",' . $comensales;
          $mysql .= ',' . $tiempo . ',' . $dificultad . ',"' . $login . '"';
          $mysql .= ')';

          if( mysqli_query($link,$mysql) )
          { // Se han insertado los datos del registro
            // Se saca el id del nuevo registro
            $mysql = "select MAX(id) as id from receta";
            if( $res = mysqli_query($link,$mysql) )
            {
              $registro = mysqli_fetch_assoc($res);
              $id_registro = $registro['id'];
            }
            else $id_registro = -1;
            $RESPONSE_CODE = 200;
            $R = array('RESULTADO' => 'OK', 'CODIGO' => $RESPONSE_CODE, 'ID' => $id_registro);
          }
          else
          {
            $RESPONSE_CODE = 500;
            $R = array('RESULTADO' => 'ERROR', 'CODIGO' => $RESPONSE_CODE, 'DESCRIPCION' => 'Error de servidor.');
          }
        }
        else
        { // El $ID es numérico por lo que voy a hacer la votación o insertar ingredientes (, o fotos)
          switch(array_shift($RECURSO))
          {
            case 'voto':
                $VOTO = array_shift($RECURSO);
                $mysql = "update receta set ";
                if($VOTO==1)
                    $mysql .= "positivos = positivos+1";
                else
                    $mysql .= "negativos = negativos+1";
                $mysql .= " where id=" . $ID;

                if( mysqli_query($link,$mysql) )
                {
                    $RESPONSE_CODE = 200;
                    $R = array('RESULTADO' => 'OK', 'CODIGO' => $RESPONSE_CODE, 'ID' => $ID, 'VOTO' => ($VOTO==1?'POSITIVO':'NEGATIVO') );
                }
                else
                {
                    $RESPONSE_CODE = 500;
                    $R = array('RESULTADO' => 'ERROR', 'CODIGO' => $RESPONSE_CODE, 'DESCRIPCION' => 'Error de servidor.');
                }
              break;
            case 'ingredientes': // insertar los ingredientes
                $ingredientes = json_decode(stripslashes(sanatize($PARAMS['i'])));
                $mysql = 'insert into ingrediente(nombre, id_receta) values ';
                foreach ($ingredientes as $nombre) {
                  $mysql .= '("' . $nombre . '",' . $ID . '),';
                }
                if(mysqli_query($link, substr($mysql,0,-1)))
                {
                  $RESPONSE_CODE = 200;
                  $R = array('RESULTADO' => 'OK', 'CODIGO' => $RESPONSE_CODE, 'DESCRIPCION' => 'INGREDIENTES GUARDADOS CORRECTAMENTE' );
                }
                else
                {
                  $RESPONSE_CODE = 500;
                  $R = array('RESULTADO' => 'ERROR', 'CODIGO' => $RESPONSE_CODE, 'DESCRIPCION' => 'Error de servidor.');
                }
              break;
            case 'comentario':
                $texto  = sanatize(nl2br($PARAMS['texto']));
                $titulo = sanatize(nl2br($PARAMS['titulo']));
                $mysql  ='insert into comentario(id_receta,titulo,texto,autor) values(' . $ID . ',"' . $titulo . '","' . $texto . '","' . $login . '")';
                if( mysqli_query( $link, $mysql ) )
                {
                  $mysql = 'select MAX(id) as id from comentario';
                  if( $res=mysqli_query( $link, $mysql ) )
                  {
                    $row = mysqli_fetch_assoc($res);
                    $ID_COMENTARIO = $row['id'];
                    // ===============================================================
                    // Se ha subido el comentario correctamente.
                    $R = array('RESULTADO' => 'OK', 'CODIGO' => 200, 'ID' => $ID_COMENTARIO);
                    // ===============================================================
                  }
                  else
                  {
                    $RESPONSE_CODE = 500;
                    $R = array('RESULTADO' => 'ERROR', 'CODIGO' => $RESPONSE_CODE, 'DESCRIPCION' => 'Se ha producido un error al intentar guardar el comentario');
                  }
                }
              break;
            case 'foto':
                $texto  = sanatize(nl2br($PARAMS['t']));
                $mysql  ='insert into foto(id_receta,texto) values(' . $ID . ',"' . $texto . '")';

                if( mysqli_query( $link, $mysql ) )
                {
                  $mysql = 'select MAX(id) as id from foto';
                  if( $res=mysqli_query( $link, $mysql ) )
                  {
                    $row = mysqli_fetch_assoc($res);
                    $ID_FOTO = $row['id'];
                    // Se copia el fichero.
                    // Nota: Hay que tener en cuenta que la carpeta de destino debe tener permisos de
                    // escritura. En Windows no hay problema, pero en linux y mac hay que comprobarlo.
                    $ext = pathinfo($_FILES['f']['name'], PATHINFO_EXTENSION); // extensión del fichero
                    $uploadfile = $uploaddir . $ID_FOTO . '.' . $ext; // path fichero destino
                    // Se crea el directorio si no existe
                    if (!file_exists($uploaddir)) {
                        mkdir($uploaddir, 0777, true);
                    }
                    if(move_uploaded_file($_FILES['f']['tmp_name'], $uploadfile)) // se sube el fichero
                    {
                      $mysql = 'update foto set fichero="' . $ID_FOTO . '.' . $ext . '" where ID=' . $ID_FOTO;
                      mysqli_query( $link, $mysql );
                      // ===============================================================
                      // Se ha subido la foto correctamente.
                      $R = array('RESULTADO' => 'OK', 'CODIGO' => 200, 'ID' => $ID_FOTO, 'FICHERO' => $ID_FOTO . '.' . $ext);
                      // ===============================================================
                    }
                    else // if(move_uploaded_file($_FILES['foto']['tmp_name'], $uploadfile))
                    {
                      $mysql = 'delete from foto where id=' . $ID_FOTO; // se borrar el registro
                      mysqli_query( $link, $mysql );
                      $RESPONSE_CODE = 500;
                      $R = array('RESULTADO' => 'ERROR', 'CODIGO' => $RESPONSE_CODE, 'ID' => $ID_FOTO , 'DESCRIPCION' => $_FILES["f"]["error"]);
                    }
                  }
                }
              break;
          }
        }
        mysqli_query($link, "COMMIT");
    }catch(Exception $e){
        mysqli_query($link, "ROLLBACK");
    }
} // if( !comprobarSesion($login,$clave) )

// =================================================================================
// SE HACE LA CONSULTA
// =================================================================================
if( count($R)==0 && $res = mysqli_query( $link, $mysql ) )
{
  if( substr($mysql, 0, 6) == "select" )
  {
    while( $row = mysqli_fetch_assoc( $res ) )
      $R[] = $row;
    mysqli_free_result( $res );
  }
  else $R[] = $res;
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
    $RESPONSE_CODE = 500;
    $rtn = array('RESULTADO' => 'ERROR', 'CODIGO' => $RESPONSE_CODE, 'DESCRIPCION' => "Se ha producido un error al devolver los datos.");
    http_response_code($RESPONSE_CODE);
    print json_encode($rtn);
}
?>