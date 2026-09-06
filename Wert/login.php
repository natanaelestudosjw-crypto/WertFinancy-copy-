<?php
$tituloDaPagina = "Minha Primeira Página PHP";
?>
<!DOCTYPE html>
<html>
<head> 
    <link rel="stylesheet" href="style_login.css">
    <title><?php echo $tituloDaPagina; ?></title>
</head>
<body>
<h1>Bem-vindo!</h1>
<div>
    <div class="login">
        <form action="">
            <input type="text" name="Nome" placeholder="Usuario">
            <input type="text" name="Senha" placeholder="Senha">
            <button>Logar</button>
        </form>
    </div>
    <div class="outro">
        <button>Cadastrar</button>
        <button>Recuperar</button>
    </div>
</div>
</body>
</html>