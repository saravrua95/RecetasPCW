<?php
// ============================================================
// PARAMETROS GENERALES DE CONFIGURACION
// ============================================================
$uploaddir = '../../fotos/'; // La carpeta fotos se encuentra en la misma carpeta que la carpeta rest
$max_uploaded_file_size = 500 * 1024; // en Bytes (500KB)
// =================================================================================
// CONFIGURACION DE ACCESO A LA BASE DE DATOS:
// =================================================================================
$_server   = "127.0.0.1"; // IP del servidor de mysql. Si mysql está escuchando en
                          // otro puerto, por ejemplo 3307, hay que añadírselo a la
                          // IP del servidor de la siguiente manera:
                          //     $_server   = "127.0.0.1:3307";
$_dataBase = "recetas";       // Nombre de la BD en mysql
$_user     = "pcw";       // Usuario con acceso a la BD $_dataBase
$_password = "pcw";       // Contraseña del usuario
// =================================================================================
// SE ABRE LA CONEXION A LA BD
// =================================================================================
$link =  mysqli_connect($_server, $_user, $_password, $_dataBase);
if (mysqli_connect_errno()) {
  printf("Fallo en la conexión: %s\n", mysqli_connect_error());
  exit();
}
// =================================================================================
// SE CONFIGURA EL JUEGO DE CARACTERES DE LA CONEXION A UTF-8
// =================================================================================
mysqli_set_charset($link, 'utf8');
// =================================================================================
// =================================================================================

// =================================================================================
// Limpia y prepara el valor correspondiente a un parámetro recibido en el servidor
// procedente de una petición del cliente (ajax, etc)
// =================================================================================
function sanatize($valor)
{
  global $link;

  $valor_retorno = urldecode('' . $valor);
  $valor_retorno = mysqli_real_escape_string($link, $valor_retorno);

  return $valor_retorno;
}

// =================================================================================
// Comprueba si el usuario está logueado y la clave es válida:
// =================================================================================
function comprobarSesion($login, $clave)
{
    global $link;
    global $tiempo_de_sesion;

    $valorRet = false;
    $mysql    = 'select * from usuario where login="' . $login . '"';
    if( $res = mysqli_query($link, $mysql) )
    {
        $row = mysqli_fetch_assoc($res);
        if($row['clave'] == $clave)
            $valorRet = true;
    }
    else
    {
        $RESPONSE_CODE = 500;
        print json_encode( array('RESULTADO' => 'ERROR', 'CODIGO' => $RESPONSE_CODE, 'DESCRIPCION' => 'Error de servidor.') );
        exit();
    }
    return $valorRet;
}
?>