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
    $this->dateposted = calculations::convertYmd($dateposted);
    $this->lastupdate = calculations::convertDate($lastupdate);

    $filecount = calculations::numFiles("./works/$ID");

    $this->numChapters = $filecount;

  }

}

class calculations {

  static function getAge($birthdate) {
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

  static function numFiles($directory) {
    $filecount = 0;
    $files = glob($directory . "/*.txt");
    if ($files){
     $filecount = count($files);
    }

    return $filecount;
  }

  static function getChapterTitles($id) {

    $numChapters = calculations::numFiles("./works/$id");

    $chapters = array();

    for ($i = 1; $i <= $numChapters; $i++) {
      $file = fopen("./works/$id/chap_$i.txt", "r") or die("Unable to open file!");
      $content = "";
      $chaptertitle = "";

      $line = fgets($file);

      $title = explode("*TITLE*: ", $line);
      $chaptertitle = $title[1];

      array_push($chapters, $chaptertitle);

    }

    return $chapters;

  }

}

class db {

  private $session;

  public function __construct(Session $session) {
      $this->session = $session;
  }

  public function connect() {

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

    $stmt = $conn->prepare("SELECT ID, Title, Description, WordCount, Cover FROM Works WHERE Author = ? ORDER BY ID Desc");
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

    if(isset($_FILES['photo']) && file_exists($_FILES['photo']['tmp_name']) && is_uploaded_file($_FILES['photo']['tmp_name']))
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
    fwrite($file, "*TITLE*: Chapter 1\n\n");
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

    if(isset($_FILES['photo']) && file_exists($_FILES['photo']['tmp_name']) && is_uploaded_file($_FILES['photo']['tmp_name']))
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

    $stmt = $conn->prepare("SELECT ID, Author, Title, Description, Genre, Rating, Tags, WordCount, Cover FROM Works WHERE ID = ?");
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

  public function addNewChapter($id, $workinfo) {

    $conn = $this->connect();

    $title = $_POST["title"];
    $content = $_POST["content"];

    $numChapters = calculations::numFiles("./works/$id");

    $newChapterNumber = $numChapters + 1;

    $file = fopen("./works/$id/chap_$newChapterNumber.txt", "w");
    fwrite($file, "*TITLE*: $title\n\n");
    fwrite($file, $content);

    $count = str_word_count($content);

    $newWordCount = $workinfo["WordCount"] + $count;

    $curtime = time();

    $stmt = $conn->prepare("UPDATE Works SET WordCount = ?, LastUpdate = ? WHERE ID = ?");
    $stmt->bind_param('ssi', $newWordCount, $curtime, $id); // bind parameters for query
    $stmt->execute() or die('Failed to update site table: ' . \mysqli_error($conn)); // perform the query
    $stmt->close(); // close statement

  }

  public function editChapter($workID, $chapterNum) {

    $conn = $this->connect();

    $title = $_POST["title"];
    $content = $_POST["content"];

    $file = fopen("./works/$workID/chap_$chapterNum.txt", "w");
    fwrite($file, "*TITLE*: $title\n\n");
    fwrite($file, $content);

    $numChapters = calculations::numFiles("./works/$workID");
    $totalwc = 0;

    for ($i = 1; $i <= $numChapters; $i++) {
      $file = fopen("./works/$workID/chap_$i.txt", "r") or die("Unable to open file!");
      $content = "";
      $chaptertitle = "";

      while(! feof($file))
      {
        $line = fgets($file);

        if (strpos($line, "*TITLE*") !== false) {
          continue;
        }

        else {
          $content .= $line;
        }
      }

      fclose($file);

      $totalwc += str_word_count($content);
    }

    $stmt = $conn->prepare("UPDATE Works SET WordCount = ? WHERE ID = ?");
    $stmt->bind_param('si', $totalwc, $workID); // bind parameters for query
    $stmt->execute() or die('Failed to update site table: ' . \mysqli_error($conn)); // perform the query
    $stmt->close(); // close statement

  }

  public function deleteAccount() {

    $id = $this->session->get('user')->ID;

    $conn = $this->connect();

    $stmt = $conn->prepare("DELETE FROM Users WHERE ID = ?");
    $stmt->bind_param('i', $id); // bind parameters for query
    $stmt->execute() or die('Failed to update site table: ' . \mysqli_error($conn)); // perform the query
    $stmt->close(); // close statement

    $stmt2 = $conn->prepare("DELETE FROM Works WHERE Author = ?");
    $stmt2->bind_param('i', $id); // bind parameters for query
    $stmt2->execute() or die('Failed to update site table: ' . \mysqli_error($conn)); // perform the query
    $stmt2->close(); // close statement

    $conn->close();

    $this->session->invalidate();

  }

  public function deleteStory($wid) {

    $conn = $this->connect();

    $stmt2 = $conn->prepare("DELETE FROM Works WHERE ID = ?");
    $stmt2->bind_param('i', $wid); // bind parameters for query
    $stmt2->execute() or die('Failed to update site table: ' . \mysqli_error($conn)); // perform the query
    $stmt2->close(); // close statement

    $conn->close();
  }

}

class MainController extends AbstractController
{

    public function index() {
      return $this->render('index.html.twig');
    }

    public function about() {
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

      $session = $this->get('session');

      if ($_SERVER["REQUEST_METHOD"] == "POST") {

        $db = new db($session);

        $db->submitProfileUpdate();

        $id = $session->get('user')->ID;

        return $this->redirect("/user/" . $id);

      }

      else if ($session->get('user') == NULL) {
        return new Response($this->renderView('401.html.twig', array(), 401));
      }

      else {
        return $this->render('edit.html.twig');
      }
    }

    public function show_add() {

      $session = $this->get('session');

      if ($_SERVER["REQUEST_METHOD"] == "POST") {

        $db = new db($session);

        $db->postStory();

        return $this->redirect("/user/" . $session->get('user')->ID);

      }

      else if ($session->get('user') == NULL) {
        return new Response($this->renderView('401.html.twig', array(), 401));
      }

      else {
        return $this->render('add.html.twig');
      }
    }

    public function show_work($slug, $slug2 = 0) {
      $pageid = intval($slug);
      $session = $this->get('session');
      $db = new db($session);
      $workinfo = $db->getWorkPage($slug);

      if ($workinfo == NULL) {
        return $this->render('404.html.twig');
      }

      else {

        if ($workinfo->numChapters > 1 && $slug2 == 0) {
          $id = $workinfo->ID;
          return $this->redirect("/work/$id/chapter_1");
        }

        if (isset($session->get('user')->ID)) {
          $usermatch = ($workinfo->author == $session->get('user')->ID ? true : false);
        }

        else {
          $usermatch = false;
        }

        if ($slug2 == 0) {
          $slug2 = 1;
        }

        $file = fopen("./works/$pageid/chap_$slug2.txt", "r") or die("Unable to open file!");
        $content = "";
        $chaptertitle = "";

        while(! feof($file))
        {
          $line = fgets($file);

          if (strpos($line, "*TITLE*") !== false) {
            $title = explode("*TITLE*: ", $line);
            $chaptertitle = $title[1];
          }

          else {
            $content .= $line;
          }
        }

        fclose($file);

        $chapterTitles = calculations::getChapterTitles($pageid);

        return $this->render('workpage.html.twig', ['pageWork' => $workinfo, 'usermatch' => $usermatch, 'content' => $content, 'currentChapter' => $slug2, 'chaptertitle' => $chaptertitle, 'chapterTitles' => $chapterTitles]);

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
        $chapterTitles = calculations::getChapterTitles($wid);
        return $this->render('edit_work.html.twig', ['work' => $workinfo, 'chapterTitles' => $chapterTitles]);
      }

    }

    public function addChapter($slug) {

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

        $db->addNewChapter($wid, $workinfo);

        return $this->redirect("/work/$wid");

      }

      else {
        return $this->render('addChapter.html.twig');
      }

    }

    public function editChapter($slug, $slug2) {

      $wid = intval($slug);
      $chapterNum = intval($slug2);
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

        $id = $session->get('user')->ID;

        $db->editChapter($wid, $chapterNum);

        return $this->redirect("/work/$wid");

      }

      else {

        $file = fopen("./works/$wid/chap_$chapterNum.txt", "r") or die("Unable to open file!");
        $content = "";
        $chaptertitle = "";

        while(! feof($file))
        {
          $line = fgets($file);

          if (strpos($line, "*TITLE*") !== false) {
            $title = explode("*TITLE*: ", $line);
            $chaptertitle = $title[1];
          }

          else {
            $content .= $line;
          }
        }

        fclose($file);

        if (calculations::numFiles("./works/$wid") == 1) {
          return $this->render('editChapterSingle.html.twig', ['content' => $content]);
        }

        else {
          return $this->render('editChapter.html.twig', ['title' => $chaptertitle, 'content' => $content, 'x' => $x]);
        }
      }

    }

    public function deleteAccount() {

      $session = $this->get('session');
      $db = new db($session);

      $db->deleteAccount();

      return $this->redirect('/');

    }

    public function deleteStory($slug) {

      $wid = intval($slug);
      $session = $this->get('session');
      $db = new db($session);
      $workinfo = $db->getWorkInfo($wid);

      if ($session->get('user') == NULL) {
        return new Response($this->renderView('401.html.twig', array(), 401));

      }

      else if ($workinfo["Author"] != $session->get('user')->ID) {
        return new Response($this->renderView('401.html.twig', array(), 401));
      }

      else if ($workinfo == NULL) {
        return $this->render('404.html.twig');
      }

      else {

        $db->deleteStory($wid);
        $author = $workinfo["Author"];
        return $this->redirect("/user/$author");

      }

    }

    public function not_found($slug){
      return $this->render('404.html.twig');
    }

}

?>
