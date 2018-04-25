<?php

include 'SpellCorrector.php';
// make sure browsers see this page as utf-8 encoded HTML
header('Content-Type: text/html; charset=utf-8');

$MAPFILE = "UrlToHtml_Newday.csv";
$urlmap = array();
if (($handle = fopen($MAPFILE, "r")) !== FALSE) {
  while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
    $num = count($data);
    if($num !== 2){
      echo "The map file is malformed!";
      break;
    }
    $urlmap[$data[0]] = $data[1];
  }
  fclose($handle);
}

$limit = 10;
$query = isset($_REQUEST['q']) ? $_REQUEST['q'] : false;
$results = false;

if ($query)
{
  // The Apache Solr Client library should be on the include path
  // which is usually most easily accomplished by placing in the
  // same directory as this script ( . or current directory is a default
  // php include path entry in the php.ini)
  require_once('Apache/Solr/Service.php');

  // create a new solr service instance - host, port, and webapp
  // path (all defaults in this example)
  $solr = new Apache_Solr_Service('localhost', 8983, '/solr/myexample/');

  // if magic quotes is enabled then stripslashes will be needed
  if (get_magic_quotes_gpc() == 1)
  {
    $query = stripslashes($query);
  }

  // in production code you'll always want to use a try /catch for any
  // possible exceptions emitted  by searching (i.e. connection
  // problems or a query parsing error)
  $ranking = isset($_REQUEST['ranking']) ? $_REQUEST['ranking'] : 'default';
  try
  {
    $params = ($ranking == 'default') ? array() : array('sort'=>'pageRankFile desc');
    //$results = $solr->search($query, 0, $limit);
    $results = $solr->search($query, 0, $limit, $params);
  }
  catch (Exception $e)
  {
    // in production you'd probably log or email this error to an admin
    // and then show a special message to the user but for this example
    // we're going to show the full exception
    die("<html><head><title>SEARCH EXCEPTION</title><body><pre>{$e->__toString()}</pre></body></html>");
  }
}

?>
<html>
  <head>
    <title>CSCI-572 Homework 5 by Zhiyuan Wang</title>
  </head>
  <link rel="stylesheet" href="http://code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
  <script src="http://code.jquery.com/jquery-1.10.2.js"></script>
  <script src="http://code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
  <body>
    <p>CSCI-572 Homework 5</p><p>Zhiyuan Wang, 3290218825</p>
    <form  accept-charset="utf-8" method="get">
      <label for="q">Search:</label>
      <input id="q" name="q" type="text" value="<?php echo htmlspecialchars($query, ENT_QUOTES, 'utf-8'); ?>"/><br/>
      <input id="default" name="ranking" type="radio" value="default" checked="checked"/><label for="default">Default</label>
      <input id="pagerank" name="ranking" type="radio" value="pagerank"/><label for="pagerank">Page Rank</label>
      <br/><input type="submit"/>
    </form>

    <script type="text/javascript">
      $(function() {
          var URL_PREFIX = "http://localhost/suggest.php?q=";
          $("#q").autocomplete({
              source : function(request, response) {
                  var lastword = $("#q").val().toLowerCase().split(" ").pop(-1);
                  var URL = URL_PREFIX + lastword;
                  $.ajax({
                      url : URL,
                      success : function(data) {
                        json = JSON.parse(data);
                          var lastword = $("#q").val().toLowerCase().split(" ").pop(-1);
                          var suggestions = json.suggest.suggest[lastword].suggestions;
                          if (json["correction"]["term"] !== $("#q").val()) {
                            duplicate = false;
                            for(i in suggestions) {
                              if(suggestions[i].term === json["correction"]["term"]){
                                duplicate = true;
                                break;
                              }
                            }
                            if(!duplicate) {
                              suggestions.unshift(json["correction"]);
                            }
                          }
                          suggestions = $.map(suggestions, function (value, index) {
                              var prefix = "";
                              var query = $("#q").val();
                              var queries = query.split(" ");
                              if (queries.length > 1) {
                                  var lastIndex = query.lastIndexOf(" ");
                                  prefix = query.substring(0, lastIndex + 1).toLowerCase();
                              }
                              if (!/^[0-9a-zA-Z]+$/.test(value.term)) {
                                  return null;
                              }
                              return prefix + value.term;
                          });
                          response(suggestions.slice(0, 5));
                      },
                  });
              },
              minLength : 1
          });
      });
    </script>

<?php
// display results
if ($results)
{
  $total = (int) $results->response->numFound;
  $start = min(1, $total);
  $end = min($limit, $total);
  if($total == 0){
    $strs = explode(" ", $query);
    $doyoumean = "";
    foreach($strs as $word){
      $doyoumean = $doyoumean.SpellCorrector::correct($word)." ";
    }
?>
    <span style="color: red;">Did you mean: </span><br>
    <b><i><a style="text-decoration:none; color: rgb(0,0,230);" href='http://localhost/?ranking=default&q=<?php echo htmlentities($doyoumean); ?>'><?php echo $doyoumean; ?></a></i></b><br>
<?php
  }
?>
    <div>Results <?php echo $start; ?> - <?php echo $end;?> of <?php echo $total; ?>:</div>
    <ol>
<?php
// iterate result documents
  foreach ($results->response->docs as $doc)
  {
  	// iterate document fields / values
  	$contents = array();
  	foreach ($doc as $field => $value)
  	{
  		if($field == 'og_url' || $field == 'title' || $field == 'og_description' || $field == 'id')
  			$contents[$field] = $value;
  	}
  	if(!array_key_exists('og_url', $contents)){
  		//check url with csv file
  		$contents['og_url'] = $urlmap[$targetid];
  	}
  	if(!array_key_exists('og_description', $contents)){
  		$contents['og_description'] = 'NA';
  	}
    $name = end(explode('/', $doc->id));
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTMLFile("./CrawlData/".$name);
    libxml_clear_errors();
    $tags=$dom->getElementsByTagName('p');
    $content = $dom->saveHTML();
    $snipet = "NA";
    $arr_old = explode(" ", $query);
    $arr = array();
    foreach($arr_old as $keyword){
      array_push($arr, strtolower($keyword));
    }
    $found = False;
    foreach ($tags as $tag) {
      $p = $tag->nodeValue."\n";
      $sentences = explode(". ", $p);
      foreach($sentences as $sentence){
        $notfound = False;
        foreach ($arr as $keyword) {
          $position = stripos($sentence, $word);
          if($position == False) {
            $notfound = True;
            break;
          }
        }
        if($notfound == False){
          $snipet = $sentence;
          $found = True;
          break;
        }
      }
      if($found){
        break;
      }
    }
    if($found == False){
      foreach ($tags as $tag) {
        $p = $tag->nodeValue."\n";
        foreach ($arr as $word) {
          $position = stripos($p, $word);
          if($position !== False) {
            $end = strpos($content, ".", $position);
            $start = strrpos(substr($p , 0, $position), ".") + 0;
            $len =  $end - $start + 1;
            $snipet = substr($p, $start, $len);
            $found = True;
            break;
          }
        }
        if($found) {
          break;
        }
      }
    }
    if($found){
      $len = strlen($snipet);
      if($len > 160) {
        $len = 160;
        $snipet = substr($snipet, 0, $len)."...";
      }
    }
    //$contents["snipet"] = $snipet;
?>
	<li>
	<!--<table style ="border: 1px solid black; text-align: left; border-radius:10px; ">-->
	<table style ="text-align: left;" width="50%">
	<tr><th colspan=2><a href=<?php echo "./CrawlData/".end(explode("/", $contents['id'])); ?> style="text-decoration:none;" target="_blank"><p style="font-size:18px;"><?php echo $contents['title']; ?></p></th></tr>
	<tr><th colspan=2><a href=<?php echo $contents['og_url']; ?> style="text-decoration:none;" target="_blank"><font color="green"><?php echo $contents['og_url']; ?></font></a></th></tr>
	<tr><!--<th>Description</th>--><td width="50%" style="color: gray;">
    <?php
      $punc = array(',', '.', '\'', '"');
      $res = "<p>";
      $words = explode(" ", $snipet);
      foreach ($words as $word) {
        $head = "";
        if(in_array($word[0], $punc)){
          $head = $word[0];
          $word = substr($word, 1);
        }
        $tail = "";
        if(in_array($word[strlen($word)-1], $punc)){
          $tail = $word[strlen($word)-1];
          $word = substr($word, 0, strlen($word)-1);
        }
        if(in_array(strtolower($word), $arr)){
          $res = $res.$head."<span style='font-weight:bold'>".$word."</span>".$tail." ";
        }
        else{
          $res = $res.$head.$word.$tail." ";
        }
      }
      $res = $res."</p>";
      echo $res;
    ?>
    </td></tr>
	<!--<tr><th>ID</th><td><?php echo $contents['id']; ?></td></tr>-->
	</table>
	</li>
<?php
  }
?>
    </ol>
<?php
}
?>
  </body>
</html>

