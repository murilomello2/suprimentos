<?php /* Manipulador de instalação do app local Bitrix24. App baseado em webhook. */ ?>
<!doctype html>
<html lang="pt-br">
<head><meta charset="utf-8"><title>Instalação</title></head>
<body>
<script src="//api.bitrix24.com/api/v1/"></script>
<script>
  if (window.BX24) { BX24.init(function(){ BX24.installFinish(); }); }
</script>
<p>Instalação concluída.</p>
</body>
</html>
