<?php
// FICHERO: rest/get/receta.php
// =================================================================================
// =================================================================================
// INCLUSION DE LA CONEXION A LA BD
   require_once('../configbd.php');
// =================================================================================
// =================================================================================
$METODO = $_SERVER['REQUEST_METHOD'];
// EL METODO DEBE SER GET. SI NO LO ES, NO SE HACE NADA.
if($METODO<>'GET') exit();
// PETICIONES GET ADMITIDAS:
//   rest/receta/{ID_receta} -------------> devuelve toda la información de la receta
//   rest/receta/{ID_receta}/fotos -------> devuelve todas las fotos de la receta
//   rest/receta/{ID_receta}/comentarios -> devuelve todos los comentarios de la receta
//	 rest/receta/?u={número} --------------> devuelve las últimas 'número' recetas, ordenadas de más a menos recientes
//   rest/receta/?t={texto} -> devuelve la lista de recetas que contengan en el nombre o en la elaboración al menos una de las palabras, separadas por comas ",", indicadas en texto
//	PARÁMETROS PARA LA BÚSQUEDA. DEVUELVE LOS REGISTROS QUE CUMPLAN
//  TODOS LOS CRITERIOS DE BÚSQUEDA (OPERADOR AND).
//   rest/receta/?n={nombre} -> búsqueda por nombre de la receta
//	 rest/receta/?i={ingrediente1,ingrediente2,...} -> búsqueda por ingredientes de la receta
//	 rest/receta/?e={elaboración} -> búsqueda por elaboración
//	 rest/receta/?a={login} -> búsqueda por autor
//   rest/receta/?d={dificultad} -> búsqueda por dificultad
//   rest/receta/?c={comensales} -> búsqueda por número de comensales
//   rest/receta/?di={número de minutos} -> recetas con duración mayor o igual a di
//   rest/receta/?df={número de minutos} -> recetas con duración menor o igual a df
//	 rest/receta/?pag={pagina}&lpag={número de registros por página} -> devuelve los registros que están en la página que se le pide, tomando como tamaño de página el valor de lpag

$RECURSO = explode("/", $_GET['prm']);
$PARAMS = array_slice($_GET, 1, count($_GET) - 1,true);
$ID = array_shift($RECURSO);

// =================================================================================
// CONFIGURACION DE SALIDA JSON Y CORS PARA PETICIONES AJAX
// =================================================================================
header("Access-Control-Allow-Orgin: *");
header("Access-Control-Allow-Methods: *");
header("Content-Type: application/json");
// =================================================================================
// Se prepara la respuesta
// =================================================================================
$R                   = [];  // Almacenará el resultado.
$RESPONSE_CODE       = 200; // código de respuesta por defecto: 200 - OK
$mysql               = '';  // para el SQL
$TOTAL_COINCIDENCIAS = -1;  // Total de coincidencias en la BD
// =================================================================================
$mysql  = 'select r.*,';
$mysql .= '(select f.fichero from foto f where f.id_receta=r.id order by f.id LIMIT 0,1) as fichero,';
$mysql .= '(select f.texto from foto f where f.id_receta=r.id order by f.id LIMIT 0,1) as descripcion_foto,';
$mysql .= '(select count(*) from foto f where f.id_receta=r.id) as nfotos,';
$mysql .= '(select count(*) from comentario c where c.id_receta=r.id) as comentarios';
$mysql .= ' FROM receta r';

if(is_numeric($ID)) // Se debe devolver toda la información de la receta
{
    if(count($RECURSO)<1)
        $mysql .= ' where id=' . sanatize($ID);
    else
    {
        switch (array_shift($RECURSO)) {
            case 'fotos':
                    $mysql = 'select * from foto where id_receta=' . sanatize($ID);
                break;
            case 'comentarios':
                    $mysql = 'select * from comentario where id_receta=' . sanatize($ID) . ' order by fecha desc';
                break;
            case 'ingredientes':
                    $mysql = 'select * from ingrediente where id_receta=' . sanatize($ID);
                break;
        }
    }
}
else
{ // Se utilizan parámetros
    // Se piden los últimos viajes subidos
    if(isset($PARAMS['u']) && is_numeric($PARAMS['u'])){
        $mysql .= ' order by r.fecha desc';
        $PARAMS['pag']  = 0;
        $PARAMS['lpag'] = sanatize($PARAMS['u']);
    }
    else if( isset($PARAMS['t']) ){ // búsqueda rápida
        $mysql .= ' where (false ';
        $texto = explode(',', sanatize($PARAMS['t']));
        $paraNombre = '';
        $paraElaboracion = '';
        foreach ($texto as $valor) {
            $paraNombre .= ' or nombre like "%' . $valor . '%"';
            $paraElaboracion .= ' or elaboracion like "%' . $valor . '%"';
        }
        $mysql .= $paraNombre . $paraElaboracion . ')';
    }
    else if(count($PARAMS)>0)
    {
        $mysql .= ' where';
        // BÚSQUEDA POR NOMBRE
        if( isset($PARAMS['n']) ){
            // permite incluir más de un texto separados por comas
            $mysql .= ' (false';
            $nombre = explode(',',sanatize($PARAMS['n']));
            foreach ($nombre as $valor) {
                $mysql .= ' OR nombre like "%' . $valor . '%"';
            }
            $mysql .= ')';
        }
        // BÚSQUEDA POR ELABORACIÓN
        if( isset($PARAMS['e']) )
        {
            if(substr($mysql, -5) != 'where') $mysql .= ' and';
            $mysql .= ' (false';
            $elaboracion = explode(',',sanatize($PARAMS['e']));
            foreach ($elaboracion as $valor) {
                $mysql .= ' OR elaboracion like "%' . $valor . '%"';
            }
            $mysql .= ')';
        }
        // BÚSQUEDA POR AUTOR (LOGIN)
        if( isset($PARAMS['a']) )
        {
            if(substr($mysql, -5) != 'where') $mysql .= ' and';
            $mysql .= ' autor like "%' . sanatize($PARAMS['a']) . '%"';
        }
        // BÚSQUEDA POR DIFICULTAD
        if( isset($PARAMS['d']) && is_numeric($PARAMS['d']) )
        {
            if(substr($mysql, -5) != 'where') $mysql .= ' and';
            $mysql .= ' dificultad=' . $PARAMS['d'];
        }
        // BÚSQUEDA POR NÚMERO DE COMENSALES
        if( isset($PARAMS['c']) && is_numeric($PARAMS['c']) )
        {
            if(substr($mysql, -5) != 'where') $mysql .= ' and';
            $mysql .= ' comensales=' . $PARAMS['c'];
        }
        // BÚSQUEDA POR TIEMPO
        if(isset($PARAMS['di']) && is_numeric($PARAMS['di']) )
        {
            if(substr($mysql, -5) != 'where') $mysql .= ' and';
            $mysql .= ' ' . $PARAMS['di'] . ' <= tiempo';
        }
        if(isset($PARAMS['df']) && is_numeric($PARAMS['df']) )
        {
            if(substr($mysql, -5) != 'where') $mysql .= ' and';
            $mysql .= ' ' . $PARAMS['df'] . ' >= tiempo';
        }
        // BÚSQUEDA POR INGREDIENTES
        if( isset($PARAMS['i']) ){
            if(substr($mysql, -5) != 'where') $mysql .= ' and';
            // permite incluir más de un texto separados por comas
            $mysql .= '(select count(*) from ingrediente i where i.id_receta=r.id and (false';
            $ingredientes = explode(',',sanatize($PARAMS['i']));
            foreach ($ingredientes as $valor) {
                $mysql .= ' OR i.nombre like "%' . $valor . '%"';
            }
            $mysql .= '))';
        }
    }
    // =================================================================================
    // PAGINACIÓN
    // =================================================================================
    if(isset($PARAMS['pag']) && is_numeric($PARAMS['pag'])      // Página a listar
        && isset($PARAMS['lpag']) && is_numeric($PARAMS['lpag']))   // Tamaño de la página
    {
        $pagina = sanatize($PARAMS['pag']);
        $regsPorPagina = sanatize($PARAMS['lpag']);
        $ELEMENTO_INICIAL = $pagina * $regsPorPagina;

        if(substr($mysql, -5) == 'where'){
            $mysql  = substr($mysql,0,strlen($mysql) - 6);
            $mysql .= ' order by r.fecha desc';
        }

        // =================================================================================
        // Para sacar el total de coincidencias que hay en la BD:
        // =================================================================================
        if( $res = mysqli_query( $link, $mysql ) )
        {
            $TOTAL_COINCIDENCIAS = mysqli_num_rows($res);
            mysqli_free_result( $res );
        }

        $mysql .= ' LIMIT ' . $ELEMENTO_INICIAL . ',' . $regsPorPagina;
    }
}
/*echo $mysql;
exit();*/

// =================================================================================
// SE HACE LA CONSULTA
// =================================================================================
if( strlen($mysql)>0 && count($R)==0 && $res=mysqli_query( $link, $mysql ) )
{
    $AA = array('RESULTADO' => 'OK', 'CODIGO' => '200');
    if($TOTAL_COINCIDENCIAS>-1)
    {
        $AA['TOTAL_COINCIDENCIAS'] = $TOTAL_COINCIDENCIAS;
        $AA['PAGINA'] = $pagina;
        $AA['REGISTROS_POR_PAGINA'] = $regsPorPagina;
    }
    if( substr($mysql, 0, 6) == 'select' )
    {
        while( $row = mysqli_fetch_assoc( $res ) )
          $R[] = $row;
        mysqli_free_result( $res );

        $R = array_merge( $AA, array('FILAS' => $R) );
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
    $rtn = array('RESULTADO' => 'error', 'CODIGO' => '500', 'DESCRIPCION' => "Se ha producido un error al devolver los datos.");
    http_response_code(500);
    print json_encode($rtn);
}
?>