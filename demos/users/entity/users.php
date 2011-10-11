<?php

namespace entity;

class users implements \Respect\Rest\Routable
{
    private static $db;

    public function __construct()
    {
        if (!isset(self::$db))
            self::$db = new \PDO('sqlite:/tmp/users.db');
    }

    public function get($id = null)
    {
        $users = self::$db->query("SELECT * FROM users")->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($users as $user) {
            echo "<form action='users/{$user['id']}/' method='POST'>
                <label> {$user['name']} </label>
              <input type='submit' name='_method' value='delete'>
              </form>";
        }

        echo "<form action='/users/' method='POST'><input name='name'> <input type='submit' value='Salve'></form>";
    }

    public function post()
    {
        self::$db->query("INSERT INTO users (id,name) VALUES ('{$this->getNextId()}','{$_POST['name']}')")->execute();
        header('Location:/users/index.php/users');
    }

    public function delete($id)
    {
        self::$db->query("DELETE FROM users WHERE id = '{$id}'")->execute();
        header('Location:/users/index.php/users');
    }

    protected function getNextId()
    {
        return (1 + $this->getLastId());
    }

    protected function getLastId()
    {
        $user = self::$db->query("SELECT * FROM users ORDER BY id DESC")->fetch(\PDO::FETCH_ASSOC);
        return (false === $user) ? 0 : (int) $user['id'];
    }

}

?>
