<?php
if (isset($_GET['pass']) && $_GET['pass'] == 'WTM977CRONUP22X') {

    // Função para obter um token de acesso OAuth 2.0
    function getAccessToken($client_id, $client_secret, $refresh_token) {
        $url = "https://auth.bling.com.br/oauth/token";

        $postData = array(
            'grant_type' => 'refresh_token',
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'refresh_token' => $refresh_token
        );

        $curl_handle = curl_init();
        curl_setopt($curl_handle, CURLOPT_URL, $url);
        curl_setopt($curl_handle, CURLOPT_POST, true);
        curl_setopt($curl_handle, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl_handle);
        curl_close($curl_handle);

        $json = json_decode($response);
        return $json->access_token;
    }

    // Função para executar a requisição CURL
    function execute($url, $token, $posts) {
        $curl_handle = curl_init();
        curl_setopt($curl_handle, CURLOPT_URL, $url);
        curl_setopt($curl_handle, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer $token",
            "Content-Type: application/json"
        ));
        curl_setopt($curl_handle, CURLOPT_POST, true);
        curl_setopt($curl_handle, CURLOPT_POSTFIELDS, json_encode($posts));
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl_handle);
        curl_close($curl_handle);
        return $response;
    }

    // Detalhes de autenticação OAuth 2.0
    $client_id = 'your_client_id';
    $client_secret = 'your_client_secret';
    $refresh_token = 'your_refresh_token';

    // Obtenha o token de acesso
    $token = getAccessToken($client_id, $client_secret, $refresh_token);

    // Conexão com o banco de dados
    require_once "connect.php";
    $newDate = strtotime('-15 days');
    $Date = date("Y-m-d", $newDate);

    if (isset($_GET['pedido']) && trim($_GET['pedido']) != '') {
        $sql = "SELECT * FROM json WHERE Pedido = '" . trim($_GET['pedido']) . "' AND Status = 1";
    } else {
        $sql = "SELECT * FROM json WHERE Data = '" . $Date . "' AND Status = 1";
    }

    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $pedido = $row['Pedido'];

            $check = $conn->query("SELECT * FROM notas_fiscais WHERE pedido = '" . $pedido . "' AND numero_rps != '0'");
            if (mysqli_num_rows($check) == 0) {
                $url = 'https://api.bling.com.br/nfse';
                $xml = $row['Texto'];

                $posts = array(
                    "xml" => rawurlencode($xml)
                );

                $resposta = execute($url, $token, $posts);
                echo $resposta . '<br>';

                $json = json_decode($resposta);

                // Inserção no banco de dados de notas fiscais
                if (isset($json->retorno->nfse)) {
                    foreach ($json->retorno->nfse as $obj) {
                        $conn->query("INSERT INTO notas_fiscais
                            (pedido, notaservico_id, numero_rps, serie, json)
                            VALUES (
                                '" . $pedido . "',
                                '" . $obj->nfse->id . "',
                                '" . $obj->nfse->numero_rps . "',
                                '" . $obj->nfse->serie . "',
                                '" . $resposta . "'
                            )"
                        );
                    }
                } else {
                    foreach ($json->retorno->erros as $obj) {
                        $conn->query("INSERT INTO notas_fiscais
                            (pedido, json, cod, msg)
                            VALUES (
                                '" . $pedido . "',
                                '" . $resposta . "',
                                '" . $obj->erro->cod . "',
                                '" . $obj->erro->msg . "'
                            )"
                        );
                    }
                }
            }
        }
    }
    exit;
}
?>
