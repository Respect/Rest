Respect\Rest
============

Controlador Magro para aplicações RESTful

**Este é um trabalho em andamento e não foi testado em ambientes de produção.**

Destaques:

 * Magro. Não tenta mudar como o PHP trabalha;
 * Leve. Poucas, pequenas classes.
 * Sustentável. Você pode migrar do estilo microframework para o estilo classe-controlador.
 * RESTful. O jeito certo de criar web apps.

Roadmap:

 * Passar no [litmus test](http://www.innoq.com/blog/st/2010/07/rest_litmus_test_for_web_frame.html)
 * Extras de hypermedia embutidos

Instalando
==========

### Método 1: Usando PEAR

Digite os seguintes comandos no seu terminal:

  * `pear channel-discover respect.github.com/pear`
  * `pear install respect/Rest-alpha`

Tenha certeza que o PEAR está no seu caminho de inclusão (include_path).

### Método 2: Download

  * Baixe a última versão de http://respect.github.com/pear/
  * Extraía o arquivo em uma pasta de sua preferência.
  * Adicione a pasta *library* criada ao seu caminho de inclusão (include_patch)

Guia de características
=============

Configuração
-------------

    <?php

    use Respect\Rest\Router;

    $r3 = new Router;

Roteamento Simples
--------------

    $r3->get('/', function() {
        return 'Hello World';
    });

 1. *get* é o equivalente HTTP do método GET. Você pode usar post, put, delete
    ou qualquer outro método. Você também pode usar métodos personalizados se quiser.
 2. *return* envia a string de saída para o expedidor.
 3. A rota é automaticamente despachada. Você pode definir `$r3->autoDispatched = false`
    se você quiser.

Parâmetros
----------

    $r3->get('/users/*', function($screen_name) {
        return "Hello {$screen_name}";
    });

 1. Parâmetros são definidos com `/*` no caminho da rota e passados para a
    função de retorno (callback) na mesma ordem que aparecerem.

Multiplos Parâmetros
-------------------

    $r3->get('/users/*/lists/*', function($user, $list) {
        return "List {$list} from user {$user}";
    });

Parâmetros Opcionais
-------------------

    $r3->get('/posts/*/*/*', function($year,$month=null,$day=null) {
        //list posts
    });

 1. Isso vai casar /posts/2010/10/10, /posts/2011/01 e /posts/2010
 2. Parâmetros Opcionais somente são permitidos no final do caminho da rota. Isso
    não permite parâmetros opcionais: `/posts/*/*/*/comments/*`

Pegar todos (Catch-all) parâmetros
--------------------

    $r3->get('/users/*/documents/**', function($user, $documentPath) {
        return readfile(PATH_STORAGE.$documentPath);
    });

 1. O exemplo acima vai casar `/users/alganet/documents/foo/bar/baz/anything`,
    e a string inteira será passada como um único parâmetro para o retorno;
 2. Pegar todos (Catch-all) parâmetros são definidos com asteriscos duplos \*\*.
 3. Pegar todos (Catch-all) parâmetros aparecem somente no final do caminho. Asteriscos
    duplos em qualquer outra posição serão convertidos em asteriscos simples.

Casando com qualquer método HTTP
------------------------

    $r3->any('/users/*', function($userName) {
        //do anything
    });

 1. Qualquer nétodo HTTP vai casar com essa mesma rota.
 2. Você pode descobrir o método usando o comando PHP padrão `$_SERVER['REQUEST_METHOD']`

Classes de Controladores Vinculados
-----------------------

    use Respect\Rest\Routable;

    class MyArticle implements Routable {
        public function get($id) { }
        public function delete($id) { }
        public function put($id) { }
    }

    $r3->any('/article/*', 'MyArticle');

  1. O exemplo acima vincula os métodos da classe ao método HTTP usando o mesmo
     caminho.
  2. Parâmetros são enviados para o método da classe exatamente como retorno dos
     outros exemplos.
  3. Controladores são carregados sob demanda e persistentes. A classe *MyArticle* vai
     ser instanciada somente quando uma rota casar com um de seus métodos é essa
     instancia vai ser reutilizada por outras requisições (redirecionamentos, etc).
  4. Classes precisam implementar a interface Respect\Rest\Routable;

Construtores das Classes Controladoras
-------------------------------

    $r3->any('/images/*', 'ImageController', array($myImageHandler, $myDb));

  1. Isso vai passar `$myImageHandler` e `$myDb` como parâmetros para a classe
     *ImageController*.

Instâncias Diretas
----------------

    $r3->any('/downloads/*', $myDownloadManager);

  1. O exemplo acima vai atribuir `$myDownloadManager` como um controlador.
  2. Essa instancia será reusada por Respect\Rest.

Fábricas (Factory) de Controladores Vinculados
----------------

    $r3->any('/downloads/*', 'MyControllerClass', array('Factory', 'getController'));

  1. O exemplo acima vai usar a classe MyController retornada por Factory::getController
  2. Essa instancia será reusada por Respect\Rest

Condições de Roteamento
----------------

    $r3->get('/documents/*', function($documentId) {
        //do something
    })->when(function($documentId) {
        return is_numeric($documentId) && $documentId > 0;
    });

  1. Isso vai casar a rota somente se a função de retorno em *when* casar.
  2. O parâmetro `$documentId` precisa ter o mesmo nome na ação e na condição
     (mas não precisa aparecer na mesma ordem)
  3. Você pode especificar mais de um argumento no mesmo retorno.
  4. Você pode especificar mais de um retorno: `when($cb1)->when($cb2)->when($etc)`
  5. Condições também sincronizam com parâmetros nas classes vinculadas e instâncias

Proxies de Rotas (Antes)
----------------------

    $r3->get('/artists/*/albums/*', function($artistName, $albumName) {
        //do something
    })->by(function($albumName) {
        $myLogger->logAlbumVisit($albumName);
    });

  1. Isso vai executar o retorno definido em *by* antes da ação da rota.
  2. Parâmetros também são sincronizados por nome, não ordem, como `when`.
  3. Você pode especificar mais que um parâmetro  por retorno de proxy.
  4. Você pode especificar mais que um proxy: `by($cb1)->by($cb2)->by($etc)`
  5. Um `return false` em um proxy vai parar a execução dos proxies seguintes
     e da ação da rota.
  6. Proxies também sincronizam com parâmetros nas classes vinculadas e instâncias

Proxies de Rotas (Depois)
----------------------

    $r3->get('/artists/*/albums/*', function($artistName, $albumName) {
        //do something
    })->through(function() {
        //do something nice
    });

  1. Proxies `by` serão executados antes das ações da rota, `through proxies`
     serão executados depois.
  2. Você não precisa usar os dois ao mesmo tempo.
  3. `through` pode também receber parâmetros por nome.

Rodando Dentro de uma Pasta
-----------------------

Para executar Respect\Rest dentro de alguma pasta (eg. http://localhost/my/folder), passe
essa pasta para o construtor da Rota:

    $r3 = new Router('/my/folder');

Você também pode usar sem suporte ao .htaccess/rewrite:

    $r3 = new Router('/my/folder/index.php');

Negociação de Conteúdo
-------------------

Respect atuamente suporta quatro tipos diferentes de negociação: Mimetype,
Encoding, Language and Charset. Exemplo de uso:

    $r3->get('/about', function() {
        return array('v' => 2.0);
    })->acceptLanguage(array(
        'en' => function($data) { return array("Version" => $data['v']); },
        'pt' => function($data) { return array("Versão"  => $data['v']); }
    ))->accept(array(
        'text/html' => function($data) { 
            list($k,$v)=each($data);
            return "<strong>$k</strong>: $v";
        },
        'application/json' => 'json_encode'
    ));

Como em qualquer rotina, Rotinas de negociação de conteúdo são executadas na mesma
ordem que você anexa-las a rota.


Informações sobre a licença
===================

Copyright (c) 2009-2011, Alexandre Gomes Gaigalas.
Todos direitos reservador.

Redistribuição e uso na forma de código fonte e binários, com ou sem modificações,
são permitidas respeitando as seguintes condições à saber:

* Redistribuições do código fonte devem manter a nota de direitos de cópia acima,
  essa lista de condições e o informativo seguinte.

* Redistribuições em formato binário devem reproduzir a nota de direitos de cópia acima,
  essa lista de condições e o informativo seguinte na documentação e/ou outros materiais
  providos com a distribuição.
  
* O nome de Alexandre Gomes Gaigalas ou o nome de qualquer outro contribuidor
  não pode ser usado para endossar ou promover produtos derivados desse software
  sem a permissão estrita e por escrito dos mesmos.

ESTE SOFTWARE É FORNECIDO POR OS DETENTORES DOS DIREITOS AUTORAIS E COLABORADORES 
"COMO ESTÁ" E QUALQUER GARANTIA EXPRESSA OU IMPLÍCITA, INCLUINDO, MAS NÃO SE LIMITANDO
ÀS GARANTIAS DE COMERCIALIZAÇÃO E ADEQUAÇÃO PARA UM FIM PARTICULAR SÃO REJEITADAS.
EM NENHUM CASO O PROPRIETÁRIO DE DIREITOS AUTORAIS E SEUS COLABORADORES SERÃO 
RESPONSÁVEIS POR QUALQUER DANO DIRETO, INDIRETO, INCIDENTAL, ESPECIAL, EXEMPLAR 
OU CONSEQÜENTE (INCLUINDO, MAS NÃO SE LIMITANDO À AQUISIÇÃO DE BENS OU SERVIÇOS;
PERDA DE USO, DADOS OU LUCROS OU INTERRUPÇÃO DE NEGÓCIOS) CAUSADOS E SOB QUALQUER 
TEORIA DE RESPONSABILIDADE, SEJA EM CONTRATO, RESPONSABILIDADE OBJETIVA OU DELITO
(INCLUINDO NEGLIGÊNCIA OU OUTROS) DECORRENTES DE QUALQUER FORMA DO USO DESTE

License Information
===================

Copyright (c) 2009-2011, Alexandre Gomes Gaigalas.
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

* Redistributions of source code must retain the above copyright notice,
  this list of conditions and the following disclaimer.

* Redistributions in binary form must reproduce the above copyright notice,
  this list of conditions and the following disclaimer in the documentation
  and/or other materials provided with the distribution.

* Neither the name of Alexandre Gomes Gaigalas nor the names of its
  contributors may be used to endorse or promote products derived from this
  software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

