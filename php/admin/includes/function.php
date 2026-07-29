<?php


function alertmessage(){
    echo '<div id="success-message">Product Added Successfully</div>';
    echo '<script>
        setTimeout(function() {
            var msg = document.getElementById("success-message");
            if (msg) msg.style.display = "none";
        }, 3000);
    </script>';
}

?>
