<?php
namespace classes;

class usuarios implements \Respect\Rest\Routable
{
    public function get($id = null)
    {
        echo '<pre>';

        if (is_null($id))
            var_dump($_SESSION['usuarios']);
        else
            var_dump($_SESSION['usuarios'][$id]);

        echo '</pre>';

        echo"<form action='add/' method='POST'><input name='nome'> <input type='submit'></form>";
    }

    public function post()
    {
        $_SESSION['usuarios'][] = $_POST['nome'];

        header('Location:/demos/usuarios/index.php/');
    }

}

?>
