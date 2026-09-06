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
            <input type="text" name="Nome" placeholder="Usuario" id="user">
            <input type="text" name="Email" placeholder="Email" id="email">
            <input type="text" name="Senha" placeholder="Senha" id="password1">
            <input type="text" name="Senha" placeholder="Confirmar Senha" id="password2">
            <button>Cadastrar</button>
        </form>
    </div>
    <div class="outro">
        <button>Login</button>
        <button>Recuperar</button>
    </div>
</div>
</body>
</html>