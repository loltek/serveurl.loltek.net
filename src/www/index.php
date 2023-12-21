<?php

declare(strict_types=1);
$input = [];
parse_str(file_get_contents("php://input"), $input);

if (isset($input['contenttype'])) {
    header("Content-Type: {$input['contenttype']}");
}
if (isset($input['raw'])) {
    header("Content-Length: " . strlen($input['raw']));
    echo $input['raw'];
    die();
}
if (isset($input['raw64'])) {
    $input = base64_decode(strtr($input['raw64'], '-_', '+/'), true);
    header("Content-Length: " . strlen($input));
    echo $input;
    die();
}
if (isset($input['zstd64'])) {
    $maxsize = 100 * 1024 * 1024; // rudimentary zip bomb protection..
    $input = base64_decode(strtr($input['zstd64'], '-_', '+/'), true);
    $tmpfileh = tmpfile();
    fwrite($tmpfileh, $input);
    $cmd = "zstd --decompress --stdout -qq " . escapeshellarg(stream_get_meta_data($tmpfileh)['uri']);
    $cmd  .= " | head -c " . $maxsize;
    passthru($cmd, $ret);
    if ($ret !== 0) {
        die("zstd failed..");
    }
    die();
}
// gzip64
if (isset($input['gzip64'])) {
    $maxsize = 100 * 1024 * 1024; // rudimentary zip bomb protection..
    $input = base64_decode(strtr($input['gzip64'], '-_', '+/'), true);
    $tmpfileh = tmpfile();
    fwrite($tmpfileh, $input);
    $cmd = "gzip --decompress --stdout " . escapeshellarg(stream_get_meta_data($tmpfileh)['uri']);
    $cmd  .= " | head -c " . $maxsize;
    passthru($cmd, $ret);
    if ($ret !== 0) {
        die("gzip failed..");
    }
    die();
}
// front page
// https://serveurl.loltek.net/
?>
<!DOCTYPE html>
<html>

<head>
    <title>ServeURL.loltek.net - Serving content from urls</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pako/2.1.0/pako.min.js" integrity="sha512-g2TeAWw5GPnX7z0Kn8nFbYfeHcvAu/tx6d6mrLe/90mkCxO+RcptyYpksUz35EO337F83bZwcmUyHiHamspkfg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
</head>

<body>
    <h1>Welcome to ServeURL.loltek.net</h1>
    <p>Serves content directly from URLs:</p>
    <ul>
        <li>Use <code>?raw=Hello%20World</code> to serve plain text like "Hello World."</li>
        <li>Use <code>?raw64=SGVsbG8gV29ybGQ=</code> for base64-encoded text like "Hello World."</li>
        <li>Use <code>?gzip64=H4sIAAAAAAACA_NIzcnJVwjPL8pJAQBWsRdKCwAAAA==</code> to serve content in base64-encoded gzip format.</li>
        <li>Use <code>?zstd64=KLUv_QRoWQAASGVsbG8gV29ybGTCWyQZ</code> for base64-encoded zstd compressed content.</li>
        <li>Use <code>?contenttype=text%2Fplain</code> to set the content type. (Default is text/html;charset=utf-8 ) </li>
    </ul>
    <p>Example: <a target="_blank" href="https://serveurl.loltek.net/?gzip64=H4sIAAAAAAACAy2QwUrEMBRFf-WRfaaKHUE7GfeDmxmF0WWavDaBpAkvL4b69Vad3YXD4cA9-Il0RGjeslNi_3gnwKGfHSvxcL8XUMgo4Zhzee661tpuTZXriDuTYodxRNvZc-vb03X-MOeX4pU8LdPp9eL6_vo2f1-CAPYcUInPVN83E768xQQ56BVJwF9_TGSRlNjqOoTUlNDGYEBKERlpAF05_RoDmODzmDRZ2cgzDoCLoTUzWhnRej3AvFIqJuWNZW-4Ekq_yNscoOEoi9OEt9ZUQyiGEJfjofu_4_gDKcFeQhcBAAA">Embedded YouTube Video</a></p>
    You can paste text here and generate a URL:
    <textarea id="inputtext" style="width:100%;height:200px;"></textarea>
    <br />
    <label for="inputfile">or upload a file:</label>
    <input type="file" id="inputfile" style="width:100%;"><br /><br />
    <input type="button" id="generatebutton" value="Generate">
    <br />
    <script>
        inputfile.onchange = function() {
            inputtext.value = "";
            window.inputdata = "";
        };

        function Uint8ToBase64(u8Arr) {
            var CHUNK_SIZE = 0x8000; //arbitrary number
            var index = 0;
            var length = u8Arr.length;
            var result = '';
            var slice;
            while (index < length) {
                slice = u8Arr.subarray(index, Math.min(index + CHUNK_SIZE, length));
                result += String.fromCharCode.apply(null, slice);
                index += CHUNK_SIZE;
            }
            return btoa(result);
        }

        function base64url_encode(str) {
            if (str instanceof Uint8Array) {
                return Uint8ToBase64(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
            }
            return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
        }

        function urlencode(str) {
            if (str instanceof Uint8Array) {
                // this is faster but does not handle / correctly: return escape(String.fromCharCode(...str)); // https://stackoverflow.com/a/77696470/1067003
                let ret = "";
                for (let i = 0; i < str.length; ++i) {
                    const c = str[i];
                    if (
                        (c >= 0x30 && c <= 0x39) // 0-9
                        ||
                        (c >= 0x41 && c <= 0x5A) // A-Z
                        ||
                        (c >= 0x61 && c <= 0x7A) // a-z
                    ) {
                        ret += String.fromCharCode(c);
                    } else {
                        ret += "%" + c.toString(16).padStart(2, "0");
                    }
                }
                return ret;
            }
            return encodeURIComponent(str);
        }
        window.inputdata = "";

        function generate() {
            winners_and_losers_text.innerText = "(calculating...)";
            let inputdata = inputtext.value;
            if (inputdata.length === 0) {
                inputdata = window.inputdata;
            }
            if (inputdata.length === 0) {
                if (inputfile.files.length === 0) {
                    winners_and_losers_text.innerText = "";
                    alert("Please paste text or upload a file.");
                    return;
                }
                winners_and_losers_text.innerText = "Reading file...";
                // convert inputdata to Uint8Array
                const reader = new FileReader();
                reader.onload = function(e) {
                    console.log("loaded file");
                    window.inputdata = new Uint8Array(e.target.result);
                    generate();
                };
                reader.readAsArrayBuffer(inputfile.files[0]);
                return;
            }
            let contenttype = "";
            if (inputfile.files.length > 0) {
                const filename = inputfile.files[0].name;
                const ext = filename.split(".").pop().toLowerCase();
                if (ext === "html") {
                    //contenttype = "&contenttype=text%2Fhtml%3Bcharset%3Dutf-8";
                } else if (ext === "txt") {
                    contenttype = "&contenttype=text%2Fplain%3Bcharset%3Dutf-8";
                } else if (ext === "js") {
                    contenttype = "&contenttype=application%2Fjavascript%3Bcharset%3Dutf-8";
                } else if (ext === "css") {
                    contenttype = "&contenttype=text%2Fcss%3Bcharset%3Dutf-8";
                } else if (ext === "json") {
                    contenttype = "&contenttype=application%2Fjson%3Bcharset%3Dutf-8";
                } else if (ext === "xml") {
                    contenttype = "&contenttype=application%2Fxml%3Bcharset%3Dutf-8";
                } else if (ext === "svg") {
                    contenttype = "&contenttype=image%2Fsvg%2Bxml%3Bcharset%3Dutf-8";
                } else if (ext === "png") {
                    contenttype = "&contenttype=image%2Fpng";
                } else if (ext === "jpg" || ext === "jpeg") {
                    contenttype = "&contenttype=image%2Fjpeg";
                } else if (ext === "gif") {
                    contenttype = "&contenttype=image%2Fgif";
                } else if (ext === "webp") {
                    contenttype = "&contenttype=image%2Fwebp";
                } else if (ext === "ico") {
                    contenttype = "&contenttype=image%2Fx-icon";
                } else if (ext === "mp4") {
                    contenttype = "&contenttype=video%2Fmp4";
                } else if (ext === "webm") {
                    contenttype = "&contenttype=video%2Fwebm";
                } else if (ext === "ogg") {
                    contenttype = "&contenttype=video%2Fogg";
                } else if (ext === "mp3") {
                    contenttype = "&contenttype=audio%2Fmpeg";
                }
            }
            const base = "https://serveurl.loltek.net/";
            generated_raw.value = base + "?raw=" + urlencode(inputdata) + contenttype;
            generated_raw_label.innerText = "Raw (" + generated_raw.value.length + " bytes)";
            generated_raw64.value = base + "?raw64=" + base64url_encode(inputdata) + contenttype;
            generated_raw64_label.innerText = "Raw64 (" + generated_raw64.value.length + " bytes)";
            generated_gzip64.value = base + "?gzip64=" + base64url_encode(pako.gzip(inputdata, {
                level: 9
            })) + contenttype;
            generated_gzip64_label.innerText = "Gzip64 (" + generated_gzip64.value.length + " bytes)";
            // now make the smallest green and the largest red
            let min = Math.min(generated_raw.value.length, generated_raw64.value.length, generated_gzip64.value.length);
            let max = Math.max(generated_raw.value.length, generated_raw64.value.length, generated_gzip64.value.length);
            let winners = [];
            let losers = [];
            if (generated_raw.value.length === min) {
                generated_raw_label.style.color = "green";
                winners.push("Raw");
            } else if (generated_raw.value.length === max) {
                generated_raw_label.style.color = "red";
                losers.push("Raw");
            } else {
                generated_raw_label.style.color = "black";
            }
            if (generated_raw64.value.length === min) {
                generated_raw64_label.style.color = "green";
                winners.push("Raw64");
            } else if (generated_raw64.value.length === max) {
                generated_raw64_label.style.color = "red";
                losers.push("Raw64");
            } else {
                generated_raw64_label.style.color = "black";
            }
            if (generated_gzip64.value.length === min) {
                generated_gzip64_label.style.color = "green";
                winners.push("Gzip64");
            } else if (generated_gzip64.value.length === max) {
                losers.push("Gzip64");
                generated_gzip64_label.style.color = "red";
            } else {
                generated_gzip64_label.style.color = "black";
            }
            let text = "Winner";
            if (winners.length > 1) {
                text += "s";
            }
            text += ": <span style='color:green;'>" + winners.join(", ") + "</span> Loser";
            if (losers.length > 1) {
                text += "s";
            }
            text += ": <span style='color:red'>" + losers.join(", ") + "</span>";
            winners_and_losers_text.innerHTML = text;
        }
        generatebutton.onclick = generate;
    </script>
    <span id="winners_and_losers_text"></span>
    <br />
    <label for="generated_raw" id="generated_raw_label">Raw (0 bytes)</label>
    <input type="text" id="generated_raw" style="width:100%;" readonly><br />
    <label for="generated_raw64" id="generated_raw64_label">Raw64 (0 bytes)</label>
    <input type="text" id="generated_raw64" style="width:100%;" readonly><br />
    <label for="generated_gzip64" id="generated_gzip64_label">Gzip64 (0 bytes)</label>
    <input type="text" id="generated_gzip64" style="width:100%;" readonly><br />
    <script>
        document.querySelectorAll("input[type=text],textarea").forEach(function(el) {
            el.value = "";
        });
    </script>
    <p>The server is meticulously configured to support very long URLs, with a limit yet to be discovered. Successful tests have been conducted with URLs reaching up to 200,000 bytes.</p>

    Here is a bash script suitable for /usr/local/bin/serveurl
    <pre>
#!/bin/bash

url="https://serveurl.loltek.net/";
compressor="";
if [ -x "$(command -v zstd)" ]; then
    compressor="zstd -19 --compress --keep --stdout";
    decompressor="zstd -d --stdout";
    url+="?zstd64=";
elif [ -x "$(command -v gzip)" ]; then
    compressor="gzip -9 --compress --keep --no-name --stdout";
    decompressor="gzip -d --stdout";
    url+="?gzip64=";
else
    compressor="cat";
    url+="?raw64=";
fi
if [ -z "$1" ]; then
    compressed=$($compressor | base64 -w 0);
    contenttype=$(echo "$compressed" | base64 -d | $decompressor | file --brief --mime-type -);
else
    contenttype=$(file --brief --mime-type "$1");
    compressed=$($compressor "$1" | base64 -w 0);
fi
contenttype=${contenttype//text\/x-*/text\/plain\;%2Fcharset\=utf-8};
contenttype=${contenttype//\//%2F};
contenttype=${contenttype//=/%3D};
contenttype=${contenttype//;/%3B};
compressed=${compressed//+/-};
compressed=${compressed//\//_};
url+=$compressed;
url+="&contenttype="$contenttype;
echo "$url";
# if xclip is installed, copy the url to clipboard
if [ -x "$(command -v xclip)" ]; then
    echo "$url" | xclip -selection clipboard;
fi
    </pre>
</body>

</html>