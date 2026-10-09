<?php
if(session_status()===PHP_SESSION_NONE) session_start();
function flash(string $key, ?string $value=null){
  if($value!==null){ $_SESSION['flash'][$key]=$value; return; }
  if(!empty($_SESSION['flash'][$key])){ 
    $v = $_SESSION['flash'][$key]; 
    unset($_SESSION['flash'][$key]); 
    return $v; 
  }
  return null;
}
?>
