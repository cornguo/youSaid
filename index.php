<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head><?php if(isset($_GET['name'])) $queryName = mb_convert_encoding($_GET['name'], 'utf-8', array('utf-8', 'big5')); ?>
<title>You Said<?php echo (!isset($queryName))? '':' - ' . htmlspecialchars($queryName); ?></title>
<meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
<script type="text/javascript">
function setFocus() {
    var iObj = document.getElementsByTagName('input')[0];
    var iLen = iObj.value.length;
    iObj.focus(); 
    iObj.setSelectionRange(iLen, iLen);
}
</script>
</head>
<body onload="setFocus();">
<base target='_blank' />

<?php
require_once 'settings.php';
?>

<p style="border: 1px dashed #600; background-color: #FFC; padding: 0.5em;">
    <strong>You Said</strong> is a handy tool which parse news snippets from Google News (Taiwan)
    and extracts all possible quotes from the name you queried.
</p>

<p>
    <form action="." target="_self">
        You said: <input type="text" name="name" value="<?php echo $names[rand(0, count($names)-1)]; ?>"></input>
        <input type='submit'></input>
    </form>
</p>

<p>
<?php
if (isset($queryName) && strlen($queryName) > 0) {
    $sentences = array();
    $links = array();

/* log query if necessary
    $logfile = "./log/" . date("Ymd") . '.txt';
    $logmessage = date("YmdHis") . "\t{$_SERVER['REMOTE_ADDR']}\t{$queryName}\n";
    file_put_contents($logfile, $logmessage, FILE_APPEND);
*/

    // strip invalid queries by pattern
    if (preg_match('/[\pC\pM\pP\pS]/Uu', $queryName)) {
        echo "Meow =w=?";
        return;
    }

    echo 'Source: <a href="http://news.google.com.tw">Google News</a>';
    echo '<h1>NAME = ' . $queryName . '</h1>';

    // setup cache file name
    $sentenceFile = './cache/sentence/' . strtolower($queryName) . '_' . date('Ymd_H-') . intval(date('i') / 20) * 20 . '.sentence';
    $linkFile = './cache/link/' . strtolower($queryName) . '_' . date('Ymd_H-') . intval(date('i') / 20) * 20 . '.link';

    // if cache file does not exist or filesize is small, fetch and calculate data
    if (!file_exists($sentenceFile) || filesize($sentenceFile) < 10) {

        // get related name aliases, if any
        $nameArr = array($queryName);
        $relatedList = file('names.txt', FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES);
        foreach ($relatedList as $nameList) {
            if (strstr($nameList, $queryName)) {
                $nameArr = explode(',', $nameList);
                asort($nameArr);
                $nameArr = array_unique($nameArr);
            }
        }

        // fetch and extract sentences from snippets of Google News, name by name
        foreach ($nameArr as $name) {

            $name = htmlspecialchars($name);

            // tricky part: add trigger characters to get effective snippets
            $url = 'https://news.google.com.tw/news/feeds?hl=zh-TW&rls=zh-tw&q='
                    . urlencode($name) . '+%28' . urlencode(implode(' OR ', $trigger))
                    . '%29&um=1&ie=UTF-8&num=100';

            $filename = './cache/news/' . strtolower($name) . '_' . date('Ymd') . '.cache';

            // cache results from Google News to suppress queries
            if (file_exists($filename) && time() - filemtime($filename) < 3600) {
                $data = file_get_contents($filename);
            } else {
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $data = curl_exec($ch);
                curl_close($ch);
                file_put_contents($filename, $data);
            }

            echo '<!-- [' . $name . '] cache time: ' . date('Y-m-d H:i', filemtime($filename)) . ' -->' . PHP_EOL;

            // traversse RSS fetched and extract snippets
            $news = new SimpleXMLElement($data);
            foreach ($news->channel->item as $i) {
                $link = $i->link;
                $strings = explode('<', strip_tags($i->description, '<div><a><p><span>'));
                $pubTime = strtotime($i->pubDate);
                $date = date('Y-m-d', $pubTime);
                $time = date('H:i', $pubTime);
                foreach ($strings as $string) {
                    $pos = mb_stripos($string, $name, 0, 'utf-8');
                    if (false !== $pos) {
                        // try to find sentece boundary and extract it
                        $short = mb_substr($string, $pos, 30, 'utf-8');
                        $sPos = false;
                        $ePos = false;
                        foreach ($trigger as $t) {
                            $sPos = mb_strpos($short, $t, 0, 'utf-8');
                            if (false !== $sPos && $sPos - mb_strlen($name, 'utf-8') < 10) {
                                break;
                            }
                            $sPos = false;
                        }
                        if (false !== $sPos) {
                            foreach ($endpunc as $p) {
                                $ePos = mb_strpos($short, $p, 0, 'utf-8');
                                if (false !== $ePos) {
                                    $ePos += mb_strlen($p, 'utf-8');
                                    break;
                                }
                            }
                            if (false === $ePos) {
                                $ePos = mb_strlen($short, 'utf-8');
                            }

                            $sentence = mb_substr($short, 0, $ePos, 'utf-8');
                            if (mb_strlen($sentence, 'utf-8') > mb_strlen($name, 'utf-8') + 3) {
                                if (!isset($sentences[$date])) {
                                    $sentences[$date] = array();
                                }
                                $str = '[' . $time . '] ' . $sentence;
                                $hash = md5($date . $str);
                                $sentences[$date][$hash] = $str;
                                $links[$hash] = (string)$link;
                            }
                        }
                    }
                }
            }
        }
        // save cache files
        file_put_contents($sentenceFile, json_encode($sentences));
        file_put_contents($linkFile, json_encode($links));
    } else {
        // load cache files
        $sentences = json_decode(file_get_contents($sentenceFile), true);
        $links = json_decode(file_get_contents($linkFile), true);
    }

    // sort sentence by time and strip duplicated data
    foreach ($sentences as $date => $sentArr) {
        arsort($sentArr);
        $sentences[$date] = array_unique($sentArr);
    }
    krsort($sentences);

    // prints out sentences
    foreach ($sentences as $date => $sentArr) {
        echo '<li>' . $date . '<ul>' . PHP_EOL;
        foreach ($sentArr as $hash => $sent) {
            echo '<li>' . $sent . ' (<a href="' . $links[$hash] . '">link</a>)</li>' . PHP_EOL;
        }
        echo '</li></ul>' . PHP_EOL;
    }
}
?>
</p>
</body>
</html>
