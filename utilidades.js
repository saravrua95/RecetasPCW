

function usuarioConectado(){
    let login = sessionStorage.getItem("login");
    let clave = sessionStorage.getItem("clave");

    if(login == null || clave == null){
            return false;
    }
    return true;
 }

 function muestraElementosMenu(){
     if(usuarioConectado() == true){
        document.getElementById("loginMenu").style.display = "none";
        document.getElementById("registroMenu").style.display = "none";
     }else{
        document.getElementById("nuevaRecetaMenu").style.display = "none";
        document.getElementById("salirMenu").style.display = "none";
     }
 }

 function controlAcceso(){
     if(usuarioConectado() != true){
        location.replace("index.html");
     }
 }

 function controlAccesoInverso(){
     if(usuarioConectado()){
         location.replace("index.html");
     }
 }


 function salir(){
     sessionStorage.clear();
     location.reload();
 }


 function getlogin(){
    return sessionStorage.getItem("login");
 }

 function getclave(){
    return sessionStorage.getItem("clave");
 }

 function busquedaRapida(){
    var textoBuscar = document.getElementById("textoBuscar").value;
    window.location = "buscar.html?t="+textoBuscar;
 }