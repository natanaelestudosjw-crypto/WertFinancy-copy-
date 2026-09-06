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
            <input type="text" name="Email" placeholder="Email ou Usuário" id="email">
            <button>Enviar código</button>
            <input type="text" name="codigo" placeholder="Código" id="code">
            <button>Entrar</button>
        </form>
    </div>
    <div class="outro">
        <button>Login</button>
        <button>Recuperar</button>
    </div>
</div>
</body>
</html>