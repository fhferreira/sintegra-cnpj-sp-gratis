<?php namespace Fhferreira\SintegraCnpjSpGratis;

use Exception;
use Goutte\Client;
use JansenFelipe\Utils\Utils as Utils;
use Symfony\Component\DomCrawler\Crawler;

class SintegraCnpjSpGratis
{

    /**
     * Metodo para capturar o captcha e viewstate para enviar no metodo
     * de consulta
     *
     * @param  string $cnpj CNPJ
     * @throws Exception
     * @return array Link para ver o Captcha e Cookie
     */
    public static function getParams()
    {
        $client = new Client();
        //https://www.cadesp.fazenda.sp.gov.br/Pages/Cadastro/Consultas/ConsultaPublica/ConsultaPublica.aspx
        $crawler = $client->request('GET', 'http://pfeserv1.fazenda.sp.gov.br/sintegrapfe/consultaSintegraServlet');
        $response = $client->getResponse();

        $input = $crawler->filter('input[name="paramBot"]');
        $paramBot = trim($input->attr('value'));

        $headers = $response->getHeaders();
        $cookie = $headers['Set-Cookie'][0];

        $paramBotURL = urlencode($paramBot);

        $ch = curl_init("http://pfeserv1.fazenda.sp.gov.br/sintegrapfe/imageGenerator?keycheck=" . $paramBotURL);
        $options = array(
            CURLOPT_COOKIEJAR => 'cookiejar',
            CURLOPT_HTTPHEADER => array(
                "Pragma: no-cache",
                "Origin: http://pfeserv1.fazenda.sp.gov.br",
                "Host: pfeserv1.fazenda.sp.gov.br",
                "User-Agent: Mozilla/5.0 (Windows NT 6.1; rv:32.0) Gecko/20100101 Firefox/32.0",
                "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
                "Accept-Language: pt-BR,pt;q=0.8,en-US;q=0.5,en;q=0.3",
                "Accept-Encoding: gzip, deflate",
                "Referer: http://pfeserv1.fazenda.sp.gov.br/sintegrapfe/consultaSintegraServlet",
                "Cookie: flag=1; $cookie",
                "Connection: keep-alive"
            ),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => 1,
            CURLOPT_BINARYTRANSFER => true
        );

        curl_setopt_array($ch, $options);
        $img = curl_exec($ch);
        curl_close($ch);

        if (@imagecreatefromstring($img) == false) {
            throw new Exception('Não foi possível capturar o captcha');
        }

        return array(
            'cookie' => $cookie,
            'captchaBase64' => 'data:image/png;base64,' . base64_encode($img),
            'paramBot' => $paramBot
        );
    }

    /**
     * Metodo para realizar a consulta
     *
     * @param  string $cnpj CNPJ
     * @param  string $ie IE - Não Testado
     * @param  string $paramBot ParamBot parametro enviado para validação do captcha
     * @param  string $captcha CAPTCHA
     * @param  string $stringCookie COOKIE
     * @throws Exception
     * @return array  Dados da empresa
     */
    public static function consulta($cnpj, $ie, $paramBot, $captcha, $stringCookie)
    {
        $arrayCookie = explode(';', $stringCookie);

        if (!Utils::isCnpj($cnpj)) {
            throw new Exception('O CNPJ informado não é válido.');
        }

        $client = new Client();
        #$client->getClient()->setDefaultOption('timeout', 120);

        $client->getClient()->setDefaultOption('config/curl/'.CURLOPT_TIMEOUT, 0);
        $client->getClient()->setDefaultOption('config/curl/'.CURLOPT_TIMEOUT_MS, 0);
        $client->getClient()->setDefaultOption('config/curl/'.CURLOPT_CONNECTTIMEOUT, 0);
        $client->getClient()->setDefaultOption('config/curl/'.CURLOPT_RETURNTRANSFER, true);

        $client->setHeader('Host', 'pfeserv1.fazenda.sp.gov.br');
        $client->setHeader('User-Agent', 'Mozilla/5.0 (Windows NT 6.1; rv:32.0) Gecko/20100101 Firefox/32.0');
        $client->setHeader('Accept', 'text/html,application/xhtml+xml,application/xml;q=0.9, */* ;q=0.8');
        $client->setHeader('Accept-Language', 'pt-BR,pt;q=0.8,en-US;q=0.5,en;q=0.3');
        $client->setHeader('Accept-Encoding', 'gzip, deflate');
        $client->setHeader('Referer', 'http://www.sintegra.gov.br/new_bv.html');
        $client->setHeader('Cookie', $arrayCookie[0]);
        $client->setHeader('Connection', 'keep-alive');

        $servico = strlen($cnpj) > 0 ? 'cnpj' : 'ie';
        $consultaPor = strlen($cnpj) > 0 ? 'Consulta por CNPJ' : 'Consulta por IE';

        $param = array(
            'hidFlag' => '0',
            'cnpj' => Utils::unmask($cnpj),
            'ie' => Utils::unmask($ie),
            'paramBot' => $paramBot,
            'Key' => $captcha,
            'servico' => $servico,
            'botao' => $consultaPor
        );

        $crawler = $client->request('POST', 'http://pfeserv1.fazenda.sp.gov.br/sintegrapfe/sintegra', $param);

        $imageError = 'O valor da imagem esta incorreto ou expirou. Verifique novamente a imagem e digite exatamente os 5 caracteres exibidos.';
        $checkError = $crawler->filter('body > center')->eq(1)->count();
        if ($checkError && $imageError == trim($crawler->filter('body > center')->eq(1)->text())) {
            throw new Exception($imageError, 99);
        }

        $center_ = $crawler->filter('body > center');
        if (count($center_) == 0) {
            throw new Exception('Serviço indisponível!. Tente novamente.', 99);
        }

        //self::saveFile($client);
        $html = self::parseContent($client->getResponse()->__toString());
        $crawler = new  \Symfony\Component\DomCrawler\Crawler($html);
        $data = self::parseSelectors($crawler);

        return $data;
    }

    public static function saveFile($client)
    {
        $file = fopen(getcwd() . "/crawler-sintegra.html", "w");
        fwrite($file, $client->getResponse()->__toString());
        fclose($file);
    }

    public static function parseContent($content)
    {
        $content = encodeToUtf8($content);
        $content = str_replace("&nbsp;", "", $content);
        return $content;
    }

    public static function parseSelectors($crawler)
    {
        try {
            try {
                $cnpj = $crawler->filter('body > center')->eq(3)->filter('table > tr > td')->eq(1)->filter('font')->text();
            } catch (\Exception $e) {
                $cnpj = null;
            }

            try {
                $ie = $crawler->filter('body > center')->eq(3)->filter('table > tr > td')->eq(3)->filter('font')->text();
            } catch (\Exception $e) {
                $ie = null;
            }
            try {
                $razao_social = $crawler->filter('body > center')->eq(4)->filter('table > tr > td')->eq(1)->filter('font')->text();
            } catch (\Exception $e) {
                $razao_social = null;
            }
            try {
                $logradouro = $crawler->filter('body > center')->eq(6)->filter('table > tr > td')->eq(1)->filter('font')->text();
            } catch (\Exception $e) {
                $logradouro = null;
            }
            try {
                $numero = $crawler->filter('body > center')->eq(7)->filter('table > tr > td')->eq(1)->filter('font')->text();
            } catch (\Exception $e) {
                $numero = null;
            }
            try {
                $complemento = $crawler->filter('body > center')->eq(7)->filter('table > tr > td')->eq(3)->filter('font')->text();
            } catch (\Exception $e) {
                $complemento = null;
            }
            try {
                $bairro = $crawler->filter('body > center')->eq(8)->filter('table > tr > td')->eq(1)->filter('font')->text();
            } catch (\Exception $e) {
                $bairro = null;
            }

            try {
                $municipio = $crawler->filter('body > center')->eq(9)->filter('table > tr > td')->eq(1)->filter('font')->text();
            } catch (\Exception $e) {
                $municipio = null;
            }
            try {
                $uf = $crawler->filter('body > center')->eq(9)->filter('table > tr > td')->eq(3)->filter('font')->text();
            } catch (\Exception $e) {
                $uf = null;
            }
            try {
                $cep = $crawler->filter('body > center')->eq(10)->filter('table > tr > td')->eq(1)->filter('font')->text();
            } catch (\Exception $e) {
                $cep = null;
            }
            try {
                $atividade_economica = $crawler->filter('body > center')->eq(12)->filter('table > tr > td')->eq(1)->filter('font')->text();
            } catch (\Exception $e) {
                $atividade_economica = null;
            }
            try {
                $situacao_cadastral_vigente = $crawler->filter('body > center')->eq(13)->filter('table > tr > td')->eq(1)->filter('font')->text();
            } catch (\Exception $e) {
                $situacao_cadastral_vigente = null;
            }
            try {
                $situacao_cadastral_vigente .= ' - ' .
                    $crawler->filter('body > center')->eq(13)->filter('table > tr > td')->eq(2)->filter('font')->text();
            } catch (\Exception $e) {
                $situacao_cadastral_vigente = null;
            }
            try {
                $data_situacao_cadastral = $crawler->filter('body > center')->eq(14)->filter('table > tr > td')->eq(1)->filter('font')->text();
            } catch (\Exception $e) {
                $data_situacao_cadastral = null;
            }
            try {
                $regime_de_apuracao = $crawler->filter('body > center')->eq(15)->filter('table > tr > td')->eq(1)->filter('font')->text();
            } catch (\Exception $e) {
                $regime_de_apuracao = null;
            }
            try {
                $data_credenciamento_emissor_nfe = $crawler->filter('body > center')->eq(16)->filter('table > tr > td')->eq(1)->filter('font')->text();
            } catch (\Exception $e) {
                $data_credenciamento_emissor_nfe = null;
            }
            try {
                $indicador_obrigatoriedade_nfe = $crawler->filter('body > center')->eq(17)->filter('table > tr > td')->eq(1)->filter('font')->text();
            } catch (\Exception $e) {
                $indicador_obrigatoriedade_nfe = null;
            }

            try {
                $data_inicio_obrigatoriedade_nfe = $crawler->filter('body > center')->eq(18)->filter('table > tr > td')->eq(1)->filter('font')->text();
            } catch (\Exception $e) {
                $data_inicio_obrigatoriedade_nfe = null;
            }

            $result = [];
            $result['cnpj'] = $cnpj;
            $result['ie'] = $ie;
            $result['razao_social'] = $razao_social;
            $result['logradouro'] = $logradouro;
            $result['numero'] = $numero;
            $result['complemento'] = $complemento;
            $result['bairro'] = $bairro;
            $result['municipio'] = $municipio;
            $result['uf'] = $uf;
            $result['cep'] = $cep;
            $result['atividade_economica'] = $atividade_economica;
            $result['situacao_cadastral_vigente'] = $situacao_cadastral_vigente;
            $result['situacao_cadastral_vigente'] = $situacao_cadastral_vigente;
            $result['data_situacao_cadastral'] = $data_situacao_cadastral;
            $result['regime_de_apuracao'] = $regime_de_apuracao;
            $result['data_credenciamento_emissor_nfe'] = $data_credenciamento_emissor_nfe;
            $result['indicador_obrigatoriedade_nfe'] = $indicador_obrigatoriedade_nfe;
            if ($data_inicio_obrigatoriedade_nfe == 'Acessar cadastro de outro Estado') {
                $data_inicio_obrigatoriedade_nfe = null;
            }
            $result['data_inicio_obrigatoriedade_nfe'] = $data_inicio_obrigatoriedade_nfe;
            foreach ($result as $key => $value) {
                if ($value != '' && $value != null) {
                    $result[$key] = utf8_decode($value);
                }
            }
            return $result;
        } catch (\Exception $e) {
            throw new Exception($e->getMessage() . "Dados não encontrados/Serviço Indisponível.");
        }
    }
}
