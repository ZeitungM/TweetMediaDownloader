<?php

    function DownloadImageFromTweetId( $tweet_id, $selected_images, $curl)
    {
        // curl に URL を設定する
        $tweet_id = str_replace( PHP_EOL, '', $tweet_id );
        $tweet_url = 'https://api.twitter.com/1.1/statuses/show.json?id='.$tweet_id;
        curl_setopt( $curl, CURLOPT_URL, $tweet_url);

        // curl を実行し、 JSON 形式に変換
        $curl_result = curl_exec($curl);
        $curl_result_utf8 = utf8_encode($curl_result);
        $json_array = json_decode( $curl_result_utf8, true );

        if(!isset($json_array['extended_entities']['media']))
        {
            echo "Unsupported JSON Type. json['extended_entities']['media'] is not exist.\n";
        }

        // 添付メディアが画像の場合
        if($json_array['extended_entities']['media']['0']['type']==='photo')
        {
            $i = 1;
            if( count($json_array['extended_entities']['media']) >= 2 )
            {
              foreach($image_url = $json_array['extended_entities']['media'] as $photo_i )
              {
                  if( empty($selected_images) || in_array( $i, $selected_images ) )
                  {
                    // 画像の URL と拡張子を取得し、画像のファイル名を作る
                    $image_url = $photo_i['media_url_https'];
                    $image_extension = strtolower( pathinfo($image_url)['extension']);
                    $image_name = $json_array['user']['screen_name'].".".$json_array['id'].".".$i.".".$image_extension;
                    echo "Start to save Image: ", $image_name ,"\n";

                    $image_data = file_get_contents($image_url."?name=orig");
                    file_put_contents( getenv('TWITTER_DOWNLOAD_DIRECTORY').$image_name, $image_data);
                    echo "Photo Saved: ".$image_name."\n";
                  }

                  $i++;
              }
            }
            else
            {
              // 画像の URL と拡張子を取得し、画像のファイル名を作る
              $image_url = $json_array['extended_entities']['media']['0']['media_url_https'];
              $image_extension = strtolower( pathinfo($image_url)['extension']);
              $image_name = $json_array['user']['screen_name'].".".$json_array['id'].".".$image_extension;
              echo "Start to save Image: ", $image_name ,"\n";

              $image_data = file_get_contents($image_url."?name=orig");
              file_put_contents( getenv('TWITTER_DOWNLOAD_DIRECTORY').$image_name, $image_data);
              echo "Photo Saved: ".$image_name."\n";
            }

        }
        // 添付メディアが動画の場合
        elseif($json_array['extended_entities']['media']['0']['type']==='video')
        {
            $mp4_bitrate = 0;
            $index = 0;
            $i = 0;
            // ビットレートが最大のファイルを探す
            foreach( $json_array['extended_entities']['media']['0']['video_info']['variants'] as $movie_media_i )
            {

              if($movie_media_i['content_type']==="video/mp4" && $movie_media_i['bitrate'] > $mp4_bitrate)
              {
                $mp4_bitrate = $movie_media_i['bitrate'];
                $index = $i;
              }

              $i++;
            }

            $movie_url = $json_array['extended_entities']['media']['0']['video_info']['variants'][$index]['url'];
            $movie_info = pathinfo($movie_url);
            $movie_extension = "mp4";
            $movie_name = $json_array['user']['screen_name'].".".$json_array['id'].".".$movie_extension;
            echo "Start to save Movie: ", $movie_name ,"\n";

            $movie_data = file_get_contents($movie_url);
            file_put_contents( getenv('TWITTER_DOWNLOAD_DIRECTORY').$movie_name, $movie_data);
            echo "Movie Saved: ".$movie_name."\n";
        }
        // gif アニメのとき
        elseif($json_array['extended_entities']['media']['0']['type']==='animated_gif')
        {
            $movie_url = $json_array['extended_entities']['media']['0']['video_info']['variants'][0]['url'];
            $movie_info = pathinfo($movie_url);
            $movie_extension = "mp4";
            $movie_name = $json_array['user']['screen_name'].".".$json_array['id'].".".$movie_extension;
            echo "Start to save animated gif: ", $movie_name ,"\n";

            $movie_data = file_get_contents($movie_url);
            file_put_contents( getenv('TWITTER_DOWNLOAD_DIRECTORY').$movie_name, $movie_data);
            echo "Movie Saved: ".$movie_name."\n";
        }
        echo "\n";
    }

    // URL.txt から一行ずつ読み込み
    $file_path = getenv('TWITTER_URL_FILE');
    $file_handle = fopen( $file_path, 'r' );

    // curl の設定
    $endpoint = 'https://api.twitter.com/1.1/statuses/show.json?id=';

    $curl = curl_init();
    curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, 'GET' );

    $header = array("Authorization: Bearer ".getenv('TWITTER_BEARER_TOKEN'));

    curl_setopt( $curl, CURLOPT_HTTPHEADER, $header );
    curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );

    $tweet_ids = [];
    $selected_images = [];
    $i = 0;
    while( $tweet_url = fgets($file_handle) )
    {
        // ツイートのURLは以下の形式で与えられるので、 /status/ から最後までか、 ?までを抜き出す
        //https://twitter.com/mt_fujimaru/status/1352192965704204291?s=19
        //https://twitter.com/warecommon/status/1354746039492878336
        //https://twitter.com/i/web/status/1354498563091390464

        preg_match('/(?<=\/status\/)\d+/', $tweet_url, $match);
        $tweet_id = utf8_encode($match[0]);

        preg_match_all('/(?<=,)\d+/', $tweet_url, $match);
        $selected_images = array_map( 'utf8_encode', $match[0]);

        $i++;
        // これから処理する URL を "読み込んだURLの番号: URL" の形式で表示
        echo $i, ": ", str_replace( array("\r\n", "\r", "\n"), '', $tweet_url), "\n";

        DownloadImageFromTweetId( $tweet_id, $selected_images, $curl);
    }

    curl_close($curl);
    fclose($file_handle);
?>
