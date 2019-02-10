<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

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

  public function __construct($ID, $author, $authorname, $title, $desc, $genre, $rating, $tags, $wordcount, $cover, $dateposted, $lastupdate) {

    $this->ID = $ID;
    $this->author = $author;
    $this->authorname = $authorname;
    $this->title = $title;
    $this->desc = $desc;
    $this->genre = $genre;
    $this->rating = $rating;
    $this->tags = $tags;
    $this->wordcount = $wordcount;
    $this->cover = $cover;
    if (isset($dateposted)) {
      $this->dateposted = calculations::convertYmd($dateposted);
      $this->lastupdate = calculations::convertDate($lastupdate);
    }

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

  static function withinRange($work) {
    $wc = $work->wordcount;
    if ($wc >= $min && $wc <= $max) {
      return true;
    }
    else {
      return false;
    }
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

  public function checkRegister() {

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

      mkdir('/img/uploads/user_' . $tempID);

    }

    return $message;

  }

  public function checkLogin() {

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

  public function getUserPage($uid) {

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

  public function getUserWorks($uid) {

    $uid = intval($uid);

    $conn = $this->connect();

    $stmt = $conn->prepare("SELECT ID, Title, Description, WordCount, Cover FROM Works WHERE Author = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();

    $result = $stmt->get_result();

    $data = array();

    while($line = $result->fetch_assoc()){
      array_push($data, $line);
    }

    return $data;

  }

  public function submitProfileUpdate() {

    $conn = $this->connect();

    $username = $_POST["username"];
    $email = $_POST["email"];
    $about = $_POST["about"];
    $location = $_POST["location"];

    if (isset($_POST["show"])) {
      $show = 1;
    }

    else {
      $show = 0;
    }

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

    $stmt = $conn->prepare("UPDATE Users SET Username = ?, Email = ?, ShowBday = ?, About = ?, Location = ?, UserPic = ? WHERE ID = ?");
    $stmt->bind_param('ssisssi', $username, $email, $show, $about, $location, $pic, $id); // bind parameters for query
    $stmt->execute() or die('Failed to update site table: ' . \mysqli_error($conn)); // perform the query
    $stmt->close(); // close statement

    $this->session->get('user')->username = $username;
    $this->session->get('user')->email = $email;
    $this->session->get('user')->show = boolval($show);
    $this->session->get('user')->about = $about;
    $this->session->get('user')->location = $location;
    $this->session->get('user')->pic = $pic;

  }

  public function postStory() {

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

    $wordcount = str_word_count($content);

    $addstatement = $conn->prepare("INSERT INTO Works (Author, Title, Description, Genre, Rating, Tags, WordCount, Cover, DatePosted, LastUpdate) VALUES (?, ?, ?, ?, ?, ?, ?, \"default.jpg\", ?, ?)");
    $addstatement->bind_param("isssssiss", $userid, $title, $desc, $genre, $rating, $tags, $wordcount, $date, $curdate);
    $addstatement->execute() or die('Failed to update site table: ' . \mysqli_error($conn));
    $id = $conn->insert_id;

    mkdir("./works/" . $id);

    $file = fopen("./works/" . $id . "/chap_1.txt", "w");
    fwrite($file, $content);

    mkdir('./coverimg/uploads/work_' . $id);


  }

  public function updateWork($id) {

    $conn = $this->connect();

    $title = $_POST["title"];
    $desc = $_POST["desc"];
    $genre = $_POST["genre"];
    $rating = $_POST["rating"];
    $tags = $_POST["tags"];

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
          move_uploaded_file($_FILES['photo']['tmp_name'], "./coverimg/uploads/work_$id/$new_file_name" . $ext);
          $pic = "uploads/work_$id/$new_file_name" . "$ext";

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
      $stmt = $conn->prepare("UPDATE Works SET Title = ?, Description = ?, Genre = ?, Rating = ?, Tags = ? WHERE ID = ?");
      $stmt->bind_param('sssssi', $title, $desc, $genre, $rating, $tags, $id); // bind parameters for query
    }

    else {
      $stmt = $conn->prepare("UPDATE Works SET Title = ?, Description = ?, Genre = ?, Rating = ?, Tags = ?, Cover = ? WHERE ID = ?");
      $stmt->bind_param('ssssssi', $title, $desc, $genre, $rating, $tags, $pic, $id); // bind parameters for query
    }

    $stmt->execute() or die('Failed to update site table: ' . \mysqli_error($conn)); // perform the query
    $stmt->close(); // close statement

  }

  public function getWorkPage($pid) {

    $pid = intval($pid);

    $conn = $this->connect();

    $stmt = $conn->prepare("SELECT Works.ID, Works.Author, Users.Username, Works.Title, Works.Description, Works.Genre, Works.Rating, Works.Tags, Works.WordCount, Works.Cover, Works.DatePosted, Works.LastUpdate FROM Works INNER JOIN Users ON Works.Author=Users.ID WHERE Works.ID = ?");
    $stmt->bind_param("i", $pid); // bind parameters for query
    $stmt->bind_result($tempid, $tempauth, $tempauthor, $temptitle, $tempdesc, $tempgenre, $temprating, $temptags, $tempwc, $tempcover, $tempposted, $tempupdated);
    $stmt->execute();

    $result = $stmt->get_result();

    $line = $result->fetch_assoc();

    if ($line['ID'] != NULL) {

      $work = new work($line["ID"], $line["Author"], $line["Username"], $line["Title"], $line["Description"], $line["Genre"], $line["Rating"], $line["Tags"], $line["WordCount"], $line["Cover"], $line["DatePosted"], $line["LastUpdate"]);
      return $work;
    }

    else {
      return NULL;
    }


  }

  public function getWorkInfo($id) {

    $conn=$this->connect();

    $stmt = $conn->prepare("SELECT Author, Title, Description, Genre, Rating, Tags, Cover FROM Works WHERE ID = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $result = $stmt->get_result();

    $line = $result->fetch_assoc();

    return $line;

  }

  public function generateLibrary() {

    $conn = $this->connect();

    $request = Request::createFromGlobals();

    $titlefilt = $request->query->get('title');
    $genrefilt = $request->query->get('genre');
    $ratingfilt = $request->query->get('rating');
    $wcfilt = $request->query->get('wordcount');

    if ($titlefilt == "") {
      $titlefilt = ".*";
    }
    else {
      $titlefilt = "$titlefilt?";
    }

    if ($genrefilt == "") {
      $genrefilt = ".*";
    }
    else {
      $genrefilt = "^$genrefilt$";
    }

    if ($ratingfilt == "") {
      $ratingfilt = ".*";
    }
    else {
      $ratingfilt = "^$ratingfilt$";
    }

    $stmt = $conn->prepare("SELECT Works.ID, Works.Author, Users.Username, Works.Title, Works.Description, Works.Genre, Works.Rating, Works.Tags, Works.WordCount, Works.Cover, Works.DatePosted, Works.LastUpdate FROM Works INNER JOIN Users ON Works.Author=Users.ID WHERE Title REGEXP \"$titlefilt\" AND Genre REGEXP \"$genrefilt\" AND Rating REGEXP \"$ratingfilt\" ORDER BY Works.LastUpdate DESC");
    $stmt->execute();

    $result = $stmt->get_result();

    $data = array();

    while($line = $result->fetch_assoc()){
      $work = new work($line["ID"], $line["Author"], $line["Username"], $line["Title"], $line["Description"], $line["Genre"], $line["Rating"], $line["Tags"], $line["WordCount"], $line["Cover"], $line["DatePosted"], $line["LastUpdate"]);
      array_push($data, $work);
    }

    $MIN = 0;
    $MAX = NULL;

    switch ($wcfilt) {
      case '5k':
        $MIN = 0;
        $MAX = 4999;
        break;
      case '10k':
        $MIN = 5000;
        $MAX = 9999;
        break;
      case '50k':
        $MIN = 10000;
        $MAX = 49999;
        break;
      case '100k':
        $MIN = 50000;
        $MAX = 99999;
        break;
      case '100plus':
        $MIN = 100000;
        break;
      default:
        break;
    }

    $data = array_filter($data, function($val) use ($MIN, $MAX) {

			if ($MAX != NULL && $val->wordcount > $MAX) {
				return false;
			}

			if ($MIN != NULL && $val->wordcount < $MIN) {
				return false;
			}
			else {
				return true;
			}

    });

    return $data;

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

      $session = $this->get('session');
      $db = new db($session);

      $works = $db->generateLibrary();

      return $this->render('library.html.twig', ['works' => $works]);

    }

    public function showlogin() {
      if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $session = $this->get('session');
        $db = new db($session);
        $message = $db->checkLogin();
        if (isset($message)) {
          return $this->render('login.html.twig', ['message' => $message]);
        }
        else {
          return $this->redirect('/');
        }
      }

      else if ($this->get('session')->get('user') != NULL){
        return $this->redirect("/user/" . $this->get('session')->get('user')->ID);

      }

      else {
        return $this->render('login.html.twig');
      }
    }

    public function showregister() {

      if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $session = $this->get('session');
        $db = new db($session);
        $message = $db->checkRegister();

        if (isset($message)) {
          return $this->render('register.html.twig', ['message' => $message]);
        }

        else {
          return $this->redirect('/');
        }
      }


      else if ($this->get('session')->get('user') != NULL){
        return $this->redirect("/user/" . $this->get('session')->get('user')->ID);
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
      $userinfo = $db->getUserPage($slug);

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

        $works = $db->getUserWorks($pageid);

        $totalwc = 0;

        foreach ($works as $w) {
          $totalwc += $w["WordCount"];

        }

        $userinfo->wordcount = $totalwc;

        return $this->render('userpage.html.twig', ['pageUser' => $userinfo, 'works' => $works, 'usermatch' => $usermatch]);
      }
    }

    public function edit_profile() {
      if ($_SERVER["REQUEST_METHOD"] == "POST") {


        $session = $this->get('session');
        $db = new db($session);

        $db->submitProfileUpdate();

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

        $db->postStory();

        return $this->redirect("/user/" . $session->get('user')->ID);

      }

      else {
        return $this->render('add.html.twig');
      }
    }

    public function show_work($slug) {
      $pageid = intval($slug);
      $session = $this->get('session');
      $db = new db($session);
      $workinfo = $db->getWorkPage($slug);

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

        $file = fopen("./works/$pageid/chap_1.txt", "r") or die("Unable to open file!");
        $content = "";

        while(! feof($file))
        {
        $content .= fgets($file). "<br /><br />";
        }

        fclose($file);

        return $this->render('workpage.html.twig', ['pageWork' => $workinfo, 'usermatch' => $usermatch, 'content' => $content]);
      }

      return $this->render('workpage.html.twig');

    }

    public function edit_work($slug) {

      $wid = intval($slug);
      $session = $this->get('session');
      $db = new db($session);
      $workinfo = $db->getWorkInfo($slug);

      if ($session->get('user') == NULL) {
        return new Response($this->renderView('401.html.twig', array(), 401));

      }

      else if ($workinfo["Author"] != $session->get('user')->ID) {
        return new Response($this->renderView('401.html.twig', array(), 401));
      }

      else if ($workinfo == NULL) {
        return $this->render('404.html.twig');
      }

      if ($_SERVER["REQUEST_METHOD"] == "POST") {


        $session = $this->get('session');
        $db = new db($session);

        $id = $session->get('user')->ID;

        $db->updateWork($wid);

        return $this->redirect("/work/" . $wid);

      }

      else {
        return $this->render('edit_work.html.twig', ['work' => $workinfo]);
      }

    }

    public function not_found($slug)
    {
      return $this->render('404.html.twig');
    }


}

?>
