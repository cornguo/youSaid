<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head><?php if(isset($_GET['name'])) $queryName = mb_convert_encoding($_GET['name'], 'utf-8', array('utf-8', 'big5')); ?>
<title>You Said<?php echo (!isset($queryName))? "":" - " . htmlspecialchars($queryName); ?></title>
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
$names = array(
        "馬英九", "吳敦義", "王如玄", "金溥聰",
        "蔡英文", "陳菊", "蘇貞昌", "李宗瑞",
        "郭台銘", "蔡衍明", "陳保基", "謝長廷",
        "王郁琦", "消息人士", "民眾", "陳冲",
        "施顏祥", "尹啟銘", "蕭萬長", "江宜樺",
        "蕭家淇", "林飛帆", "陳為廷"
);
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

    if (preg_match("/[\pC\pM\pP\pS]/Uu", $queryName)) {
        echo "Meow =w=?";
        return;
    }

    echo "Source: <a href=\"http://news.google.com.tw\" target=\"_blank\">Google News</a>";
    echo "<h1>NAME = {$queryName}</h1>";

    $sentenceFile = "./cache/sentence/" . strtolower($queryName) . "_" . date("Ymd_H-") . intval(date("i") / 20) * 20 . ".sentence";
    $linkFile = "./cache/link/" . strtolower($queryName) . "_" . date("Ymd_H-") . intval(date("i") / 20) * 20 . ".link";

    if (!file_exists($sentenceFile) || filesize($sentenceFile) < 10) {

        $nameArr = array($queryName);
        $relatedList = file('names.txt', FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES);
        foreach ($relatedList as $nameList) {
            if (strstr($nameList, $queryName)) {
                $nameArr = explode(',', $nameList);
                asort($nameArr);
                $nameArr = array_unique($nameArr);
            }
        }

        foreach ($nameArr as $name) {
            $name = htmlspecialchars($name);

            $trigger = array(
                "「", "：", "說", "表示", "哽咽", "指示", "表達", "希望", "期盼",
                "呼籲", "喊", "宣示", "期待", "指出", "稱", "解釋", "聲明", "強調",
                "發表", "致詞", "陳情", "提出", "質疑", "下令", "諷刺", "譏"
            );

            $endpunc = array(
                "。", "！", "？", "!", "?", ".", "」"
            );

            $url = "https://news.google.com.tw/news/feeds?hl=zh-TW&rls=zh-tw&q=" . urlencode($name) . "+%28" . urlencode(implode(" OR ", $trigger)) . "%29&um=1&ie=UTF-8&num=100";

            $filename = "./cache/news/" . strtolower($name) . "_" . date("Ymd") . ".cache";

            if (file_exists($filename) && time() - filemtime($filename) < 3600) {
                $data = file_get_contents($filename);
            } else {
                $data = shell_exec("/usr/local/bin/wget --no-check-certificate -qO- '{$url}'");
                file_put_contents($filename, $data);
            }

            echo "<!-- [{$name}] cache time: " . date("Y-m-d H:i", filemtime($filename)) . " -->\n";

            $news = new SimpleXMLElement($data);
            foreach ($news->channel->item as $i) {
                $link = $i->link;
                $strings = explode("<", strip_tags($i->description, "<div><a><p><span>"));
                $date = date("Y-m-d", strtotime($i->pubDate));
                $time = date("H:i", strtotime($i->pubDate));
                foreach ($strings as $string) {
                    $pos = mb_stripos($string, $name, 0, 'utf-8');
                    if (false !== $pos){
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
                                $str = "[{$time}] {$sentence}";
                                $hash = md5($date . $str);
                                $sentences[$date][$hash] = $str;
                                $links[$hash] = (string)$link;
                            }
                        }
                    }
                }
            }
        }
        file_put_contents($sentenceFile, json_encode($sentences));
        file_put_contents($linkFile, json_encode($links));
    } else {
        $sentences = json_decode(file_get_contents($sentenceFile), true);
        $links = json_decode(file_get_contents($linkFile), true);
    }

    foreach ($sentences as $date => $sentArr) {
        arsort($sentArr);
        $sentences[$date] = array_unique($sentArr);
    }
    krsort($sentences);

    foreach ($sentences as $date => $sentArr) {
        echo "<li>{$date}<ul>\n";
        foreach ($sentArr as $hash => $sent) {
            echo "<li>" . $sent . " (<a href=\"{$links[$hash]}\">link</a>)</li>\n";
        }
        echo "</li></ul>\n";
    }
}
?>
</p>
</body>
</html>
