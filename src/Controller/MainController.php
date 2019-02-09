<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

class user {

  public function __construct($ID, $username, $email, $joined, $bday, $show, $about, $location, $pic) {

    $this->ID = $ID;
    $this->username = $username;
    $this->email = $email;
    $this->joined = calculations::convertYmd($joined);
    $this->bday = $bday;
    $this->show = boolval($show);
    $this->age = calculations::getAge($bday);
    $this->about = $about;
    $this->location = $location;
    $this->pic = $pic;

  }

}

class work {

  public function __construct($ID, $author, $authorname, $title, $desc, $genre, $rating, $tags, $cover, $dateposted, $lastupdate) {

    $this->ID = $ID;
    $this->author = $author;
    $this->authorname = $authorname;
    $this->title = $title;
    $this->desc = $desc;
    $this->genre = $genre;
    $this->rating = $rating;
    $this->tags = $tags;
    $this->cover = $cover;
    $this->dateposted = calculations::convertYmd($dateposted);
    $this->lastupdate = calculations::convertDate($lastupdate);

  }

}

class calculations {

  static function getAge($birthdate)
  {
    $datetime1 = new \DateTime($birthdate);
    $datetime2 = new \DateTime("now");
    $interval = $datetime1->diff($datetime2);

    return $interval->format('%y');
  }

  static function convertDate($timestamp) {

    $date = new \DateTime();

    $date->setTimestamp($timestamp);
    return $date->format('F d, Y');

  }

  static function convertYmd ($ymd) {
    $format = 'Y-m-d';
    $date = \DateTime::createFromFormat($format, $ymd);
    return $date->format('F d, Y') . "\n";

  }

}

class db {

  private $session;

  public function __construct(Session $session)
  {
      $this->session = $session;
  }

  public static function connect()
  {

    $info = file_get_contents('config.json');
    $dblogin = json_decode($info);
    $dbname = $dblogin->dbname;
    $username = $dblogin->username;
    $dbpass = $dblogin->dbpass;
    $db = $dblogin->db;

    $conn = new \mysqli($dbname, $username, $dbpass, $db); // connect to database

    if ($conn->connect_error)
    {
      die("Connection failed: " . $conn->connect_error);
    }

    else {
      return $conn;
    }

  }

  public function check_register() {

    $conn = $this->connect();

    $user = $_POST["username"];
    $email = $_POST["email"];
    $birthdate = $_POST["birthdate"];
    $password = $_POST["password"];
    $password2 = $_POST["passwordConf"];

    $message = NULL;

    $age = calculations::getAge($birthdate);
    $age = intval($age);

    $passhash = password_hash($password, PASSWORD_DEFAULT);

    $statement = $conn->prepare("SELECT Username, Email FROM Users WHERE Email = ?");
    $statement->bind_param("s", $email);
    $statement->execute();
    $statement->store_result();
    $numRows = $statement->num_rows;

    if ($user == "" || $email == "")
    {
      $message = 'Fields cannot be left blank';
    }

    else if ($age < 13) {
      $message = 'You must be at least 13 years old to create an account';
    }

    else if (strlen($password) < 8) {
      $message = 'Password must be at least 8 characters';
    }

    else if ($password != $password2) {
      $message = 'Passwords do not match';
    }

    else if ($numRows != 0){
      $message = 'User already exists';
    }

    else {
      $curdate = date('Y-m-d');
      $addstatement = $conn->prepare("INSERT INTO Users (Username, Email, Password, DateJoined, Birthdate, UserPic) VALUES (?, ?, ?, ?, ?, \"default.jpg\")" );
      $addstatement->bind_param("sssss", $user, $email, $passhash, $curdate, $birthdate);
      $addstatement->execute() or die('Failed to update site table: ' . \mysqli_error($conn));

      $statement3 = $conn->prepare("SELECT ID FROM Users WHERE Email = ?");
      $statement3->bind_param("s", $email);
      $statement3->execute();
      $statement3->bind_result($tempID);
      $statement3->fetch();

      $this->session->set('loggedin', 'true');
      $this->session->set('username', $user); // set username to session
      $this->session->set('ID', $tempID);

      $conn->close();

      mkdir('/img/uploads/' + $tempID);

    }

    return $message;

  }

  public function check_login() {

    $conn = $this->connect();

    $email = $_POST["email"]; // get submitted email
    $password = $_POST["password"]; // get submitted password

    $message = null;


    if ($email == "" || $password == "") // if a field is left blank
    {
        $message = 'Fields cannot be left blank';
    }

    else {

      $stmt = $conn->prepare("SELECT * FROM Users WHERE Email = ? ");

      $stmt->bind_param("s", $email); // bind parameters for query
      $stmt->execute();
      $stmt->bind_result($tempID, $tempUser, $tempEmail, $tempPass, $tempjoined, $tempbday, $tempshow, $tempabout, $temploc, $temppic); // put results into variables
      $stmt->fetch(); // FETCH DATA V IMPORTANT OR WILL NOT WORK
      $conn->close();

      if ($tempID == "") // if no user match was found
      {
          $message = 'User does not exist';
      }

      else if (!password_verify($password, $tempPass)){
          $message = "Password incorrect";
      }

      else {

        $user = new user($tempID, $tempUser, $tempEmail, $tempjoined, $tempbday, $tempshow, $tempabout, $temploc, $temppic);
        $this->session->set('user', $user);

      }

    }

    return $message;

  }

  public function get_user_page($uid) {

    $uid = intval($uid);

    $conn = $this->connect();

    $stmt = $conn->prepare("SELECT Username, Email, DateJoined, Birthdate, ShowBday, About, Location, UserPic FROM Users WHERE ID = ? ");
    $stmt->bind_param("i", $uid); // bind parameters for query
    $stmt->execute();
    $stmt->bind_result($tempusername, $tempemail, $tempjoined, $tempbday, $tempshow, $tempabout, $temploc, $temppic);
    $stmt->fetch();
    $conn->close();

    if ($tempusername == NULL) {
      return NULL;
    }

    else {
      $user = new user($uid, $tempusername, $tempemail, $tempjoined, $tempbday, $tempshow, $tempabout, $temploc, $temppic);

      return $user;
    }

  }

  public function get_user_works($uid) {

    $uid = intval($uid);

    $conn = $this->connect();

    $stmt = $conn->prepare("SELECT ID, Title, Description, Cover FROM Works WHERE Author = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();

    $result = $stmt->get_result();

    $data = array();

    while($line = $result->fetch_assoc()){
      array_push($data, $line);
    }

    return $data;

  }

  public function submit_profile_update() {

    $conn = $this->connect();

    $username = $_POST["username"];
    $email = $_POST["email"];
    $about = $_POST["about"];
    $location = $_POST["location"];

    $id = $this->session->get('user')->ID;

    $pic = '';

    if(file_exists($_FILES['photo']['tmp_name']) && is_uploaded_file($_FILES['photo']['tmp_name']))
    {

      if($_FILES['photo']['name'])
      {
        if(!$_FILES['photo']['error'])
        {
          //modify the future file name
          $tmp = substr($_FILES['photo']['tmp_name'], -6);
          $new_file_name = strtolower($tmp); //rename file

          $imagetypes = array(
              'image/png' => '.png',
              'image/gif' => '.gif',
              'image/jpeg' => '.jpg',
              'image/bmp' => '.bmp');
              $ext = $imagetypes[$_FILES['photo']['type']];

            //move it to where we want it to be
          move_uploaded_file($_FILES['photo']['tmp_name'], "img/uploads/user_$id/$new_file_name" . $ext);
          $pic = "/uploads/user_$id/$new_file_name" . "$ext";

        }

            //if there is an error...
        else
        {
          $message = 'The following error occurred:  '.$_FILES['photo']['error'];
          echo $message;
        }

      }

    }

    if ($pic == '') {
      $pic = $this->session->get('user')->pic;
    }

    $stmt = $conn->prepare("UPDATE Users SET Username = ?, Email = ?, About = ?, Location = ?, UserPic = ? WHERE ID = ?");
    $stmt->bind_param('sssssi', $username, $email, $about, $location, $pic, $id); // bind parameters for query
    $stmt->execute() or die('Failed to update site table: ' . \mysqli_error($conn)); // perform the query
    $stmt->close(); // close statement

    $this->session->get('user')->username = $username;
    $this->session->get('user')->email = $email;
    $this->session->get('user')->about = $about;
    $this->session->get('user')->location = $location;
    $this->session->get('user')->pic = $pic;

  }

  public function post_story() {

    $conn = $this->connect();

    $title = $_POST["title"];
    $desc = $_POST["desc"];
    $genre = $_POST["genre"];
    $rating = $_POST["rating"];
    $tags = $_POST["tags"];

    $content = $_POST["content"];

    $date = date('Y-m-d');
    $curdate = time();

    $userid = $this->session->get('user')->ID;

    $addstatement = $conn->prepare("INSERT INTO Works (Author, Title, Description, Genre, Rating, Tags, Cover, DatePosted, LastUpdate) VALUES (?, ?, ?, ?, ?, ?, \"default.jpg\", ?, ?)");
    $addstatement->bind_param("isssssss", $userid, $title, $desc, $genre, $rating, $tags, $date, $curdate);
    $addstatement->execute() or die('Failed to update site table: ' . \mysqli_error($conn));
    $id = $conn->insert_id;

    mkdir("./works/" . $id);

    $file = fopen("./works/" . $id . "/chap_1.txt", "w");
    fwrite($file, $content);

  }

  public function get_work_page($pid) {

    $pid = intval($pid);

    $conn = $this->connect();

    $stmt = $conn->prepare("SELECT * FROM Works WHERE ID = ? ");
    $stmt->bind_param("i", $pid); // bind parameters for query
    $stmt->bind_result($tempid, $tempauth, $temptitle, $tempdesc, $tempgenre, $temprating, $temptags, $tempcover, $tempposted, $tempupdated);
    $stmt->execute();
    $stmt->fetch();
    $conn->close();

    if ($tempid != NULL) {


      $conn2 = $this->connect();
      $query= $conn2->prepare("SELECT Username FROM Users WHERE ID = ?");
      $query->bind_param("i", $tempauth);
      $query->execute();
      $query->bind_result($tempauthor);
      $query->fetch();


      $work = new work($tempid, $tempauth, $tempauthor, $temptitle, $tempdesc, $tempgenre, $temprating, $temptags, $tempcover, $tempposted, $tempupdated);
      return $work;
    }

    else {
      return NULL;
    }


  }

}

class MainController extends AbstractController
{
    /**
     * Matches / exactly
     *
     * @Route("/", name="index")
     */

    public function index()
    {
      return $this->render('index.html.twig');
    }

    public function about()
    {
      return $this->render('about.html.twig');
    }

    public function contact() {
      return $this->render('contact.html.twig');
    }

    public function categories() {
      return $this->render('categories.html.twig');
    }

    public function library() {
      return $this->render('library.html.twig');
    }

    public function showlogin() {
      if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $session = $this->get('session');
        $db = new db($session);
        $message = $db->check_login();
        if (isset($message)) {
          return $this->render('login.html.twig', ['message' => $message]);
        }
        else {
          return $this->redirect('/');
        }
      }
      else {
        return $this->render('login.html.twig');
      }
    }

    public function showregister() {

      if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $session = $this->get('session');
        $db = new db($session);
        $message = $db->check_register();
        if (isset($message)) {
          return $this->render('register.html.twig', ['message' => $message]);
        }
        else {
          return $this->redirect('/');
        }
      }

      else {

        return $this->render('register.html.twig');

      }

    }

    public function logout() {
      $this->get('session')->invalidate();
      return $this->redirect('/');
    }

    public function user_page($slug) {
      $pageid = intval($slug);
      $session = $this->get('session');
      $db = new db($session);
      $userinfo = $db->get_user_page($slug);

      if ($userinfo == NULL) {
        return $this->render('404.html.twig');
      }

      else {

        if (isset($session->get('user')->ID)) {
          $usermatch = ($userinfo->ID == $session->get('user')->ID ? true : false);
        }

        else {
          $usermatch = false;
        }

        $works = $db->get_user_works($pageid);

        return $this->render('userpage.html.twig', ['pageUser' => $userinfo, 'works' => $works, 'usermatch' => $usermatch]);
      }
    }

    public function edit_profile() {
      if ($_SERVER["REQUEST_METHOD"] == "POST") {


        $session = $this->get('session');
        $db = new db($session);

        $db->submit_profile_update();

        $id = $session->get('user')->ID;

        return $this->redirect("/user/" . $id);

      }

      else {
        return $this->render('edit.html.twig');
      }
    }

    public function show_add() {
      if ($_SERVER["REQUEST_METHOD"] == "POST") {


        $session = $this->get('session');
        $db = new db($session);

        $db->post_story();

        return $this->render('add.html.twig');

      }

      else {
        return $this->render('add.html.twig');
      }
    }

    public function show_work($slug) {
      $pageid = intval($slug);
      $session = $this->get('session');
      $db = new db($session);
      $workinfo = $db->get_work_page($slug);

      if ($workinfo == NULL) {
        return $this->render('404.html.twig');
      }

      else {

        if (isset($session->get('user')->ID)) {
          $usermatch = ($workinfo->author == $session->get('user')->ID ? true : false);
        }

        else {
          $usermatch = false;
        }

        $myfile = fopen("./works/$pageid/chap_1.txt", "r") or die("Unable to open file!");
        $content = fread($myfile,filesize("./works/$pageid/chap_1.txt"));
        fclose($myfile);

        return $this->render('workpage.html.twig', ['pageWork' => $workinfo, 'usermatch' => $usermatch, 'content' => $content]);
      }

      return $this->render('workpage.html.twig');

    }

    public function not_found($slug)
    {
      return $this->render('404.html.twig');
    }


}

?>
