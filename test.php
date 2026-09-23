<?php

include "config/db.php";

if($conn){
    echo "Database Connected Successfully";
}else{
    echo "Connection Failed";
}

?>