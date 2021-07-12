<?php
declare(strict_types=1);
/*
 * @author Bc. Josef Jebavý
 * 2021-05-17
 */
include "token.php";
// ID 10975505

require 'vendor/autoload.php';

use GuzzleHttp\Client;

// je potreba prsokenovat vsechny skupiny a podskupiny a k nim uzivatele
// pro tyto skupiny je potreba ziskat projekty a knim uzivatele
// pri tech pruchodech si zapamatovat uzivatele a skupinu nebo projekt a to vcetne prav

/*
 * TODO paginator
 * data v hlavickach
 * x-next-page: 3
x-page: 2
x-per-page: 3
x-prev-page: 1
x-request-id: 732ad4ee-9870-4866-a199-a9db0cde3c86
x-runtime: 0.108688
x-total: 8
x-total-pages: 3
 */


/**
 *  trida ktera sese informace o uzivateli gitlabu
 */
class UserGitlab
{

    private $groups = [];
    private $projects = [];

    public function __construct(int $id, string $name, string $username)
    {
        $this->id = $id;
        $this->name = $name;
        $this->username = $username;

    }

    public function addProject(int $key, string $name)
    {
        //tady bych mohl kontrolvat jestli to uz existuje ale  vlastne to neni dulezite
        $this->projects[$key] = $name;
    }

    public function addGroup(int $key, string $name)
    {

        $this->groups[$key] = $name;

    }

    public function __toString()
    {
        /*
            Jan Konáš (@jan.konas)
            Groups:    [apploud/backend-testovaci-zadani (Owner)]
            Projects:  []
         * */
        $str1 = "$this->id: $this->name ($this->username)";
        $str2 = "Groups: " . implode(", ", $this->groups);
        $str3 = "Projects: " . implode(", ", $this->projects);
        return $str1 . "\n" . $str2 . "\n" . $str3 . "\n";

    }

}

class gitlabClient
{
// tabulka ktera obsahuje cisla opravneni
    private $accesLevel = [
        0 => "No access",
        5 => "Minimal access",
        10 => "Guest",
        20 => "Reporter",
        30 => "Developer",
        40 => "Maintainer",
        50 => "Owner"];
    private $usersArray = [];

    public function __construct(string $token)
    {
        $this->client = new GuzzleHttp\Client(['headers' => ['PRIVATE-TOKEN' => $token]]);
    }

    public function showInfo()
    {
        foreach ($this->usersArray as $user) {
            echo $user . "\n";
        }

        echo count($this->usersArray) . "\n";

    }

    public function process(int $id)
    {
        $this->getGroups($id);
        $this->showInfo();

    }

    public function getGroups(int $id)
    {


        //ziskani vsech potomku dane skupiny
        $groups = $this->getDescendantGroups($id);
        // var_dump($groups);

        foreach ($groups as $group) {
            // var_dump($group->id);

            $this->processGroup($group);


            //ziskani vsech projektu dane skupiny a uzivatelu daneho projektu
            $projects = $this->getProjectOfGroup($group->id);

            foreach ($projects as $project) {

                $this->processProject($project);

            }
        }


        // jeste zpracovat skupinu na  horni urovni
        $mainGroup = $this->getOneGroup($id);
        $this->processGroup($mainGroup);
        foreach ($mainGroup->projects as $project) {
            $this->processProject($project);

        }


    }

    public function processGroup($group)
    {
        //   var_dump($this->getGroupAccess($group->id));
        //ziskani vsech uzivatelu dane skupiny
        $users = $this->getUserOfGroup($group->id);
        //  var_dump($users);
        foreach ($users as $user) {
            $userId = $user->id;
            if (array_key_exists($userId, $this->usersArray)) {
                $this->usersArray[$userId]->addGroup($group->id, $group->name . " (" . $this->accesLevel[$user->access_level] . ")");
            } else {
                $userNew = new UserGitlab($userId, $user->name, $user->username);
                $userNew->addGroup($group->id, $group->name . " (" . $this->accesLevel[$user->access_level] . ")");
                $this->usersArray[$userId] = $userNew;
            }

        }
    }

    public function processProject($project)
    {
        // var_dump($this->getProjectAccess($project->id));

        $users = $this->getUsersOfProject($project->id);
        foreach ($users as $user) {
            $userId = $user->id;
            if (array_key_exists($userId, $this->usersArray)) {
                $this->usersArray[$userId]->addProject($project->id, $project->name . " (" . $this->accesLevel[$user->access_level] . ")");
            } else {
                $userNew = new UserGitlab($userId, $user->name, $user->username);
                $userNew->addProject($project->id, $project->name) . " (" . $this->accesLevel[$user->access_level] . ")";
                $this->usersArray[$userId] = $userNew;
            }

        }
    }

    public function getProjectOfGroup(int $id)
    {

        // TODO paginator
        $url = 'https://gitlab.com/api/v4/groups/' . $id . '/projects';
        return $this->getData($url);

    }

    public function getUserOfGroup(int $id)
    {

        $url = 'https://gitlab.com/api/v4/groups/' . $id . '/members/all';
        return $this->getData($url);

    }


    public function getUsersOfProject(int $id)
    {
//clenove projektu

        $url = 'https://gitlab.com/api/v4/projects/' . $id . '/members/all';
        return $this->getData($url);

    }


    public function getDescendantGroups(int $id)
    {
        //ziskani vsech potomku dane skupiny
        //TODO paginator;
        $url = 'https://gitlab.com/api/v4/groups/' . $id . '/descendant_groups';
        return $this->getData($url);

    }

    public function getOneGroup(int $id)
    {
        $url = 'https://gitlab.com/api/v4/groups/' . $id;
        return $this->getData($url);

    }


    private function getData(string $url)
    {
        $res = $this->client->request('GET', $url);
        $status = $res->getStatusCode();
        if ($status != 200) {
            echo "HTTP status error\n";
            exit(1);

        }

        echo $res->getHeaderLine('x-total-pages');
        /**/
        $body = $res->getBody();

        $json = json_decode($body . "");
        return $json;
    }

}


if (!(count($argv) == 2)) {
    echo "please add one param: ID top-level group\n";

    exit(1);
}

$id = intval($argv[1]);


$gitlab = new gitlabClient($TOKEN);
$gitlab->process($id);
