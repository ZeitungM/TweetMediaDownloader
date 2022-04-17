<?php

  class Tweet
  {
    public static $_curl;
    public static $_endpoint_get2tweet = 'https://api.twitter.com/2/tweets';
    public $_id;
    public $_author;
    public $_selected_images;

    function __construct()
    {

    }

    function GetTweetIdByRegEx($line)
    {
      // ツイートのURLは以下の形式で与えられるので、 /status/ から最後までか、 ?までを抜き出す
      //https://twitter.com/mt_fujimaru/status/1352192965704204291?s=19
      //https://twitter.com/warecommon/status/1354746039492878336
      //https://twitter.com/i/web/status/1354498563091390464

      preg_match('/(?<=\/status\/)\d+/', $line, $match);
      $this->_id = utf8_encode($match[0]);
      //echo "tweet id: ".$this->_id."\n";
    }

    function GetSelectedImagesByRegEx($line)
    {
      preg_match_all('/(?<=,)\d+/', $line, $match);
      $this->_selected_images = array_map( 'utf8_encode', $match[0]);
//      echo "selected images: ";
//      var_dump($this->_selected_images);
//      echo "\n";
    }

    function GetUsernameByAPI()
    {
      // ユーザ情報を取得する API : curl "https://api.twitter.com/2/tweets?ids=<tweet_id>&expansions=author_id&tweet.fields=created_at&user.fields=username"  -H "Authorization: Bearer AAAAAAAAAAAAAAAAAAAAABEoMAEAAAAACwO9DW%2F291bM4Cf3G59bUsySSLE%3DBPvqyMMrVoQ5iy1bqsK27dMdRXh8WboAsyBsfgP8pfBqa5RiJX"
      // curl の設定
      $curl = curl_init();
      curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, 'GET' );
      $header = array("Authorization: Bearer ".getenv('TWITTER_BEARER_TOKEN'));
      curl_setopt( $curl, CURLOPT_HTTPHEADER, $header );
      curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );

      //$endpoint = 'https://api.twitter.com/2/tweets';
      $query_tweet_id = "?ids=";
      $query_expansions = "&expansions=author_id";
      $query_user_fields = "&user.fields=username";
      $tweet_id = str_replace( PHP_EOL, '', $tweet_id );

      // user.fieldの username の取得には、expansionsの指定も必要

      $api_url = 'https://api.twitter.com/2/tweets'.$query_tweet_id.$this->_id.$query_expansions.$query_user_fields;
      curl_setopt( $curl, CURLOPT_URL, $api_url);
      $curl_result = curl_exec($curl);
      $curl_result_utf8 = utf8_encode($curl_result);
      $json_array = json_decode( $curl_result_utf8, true );

      $this->_author = $json_array["includes"]["users"][0]["username"];
      echo "author: ".$this->_author."\n";
    }

    function DownloadImage()
    {
      // curl に URL を設定する
      $curl = curl_init();
      curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, 'GET' );
      $header = array("Authorization: Bearer ".getenv('TWITTER_BEARER_TOKEN'));
      curl_setopt( $curl, CURLOPT_HTTPHEADER, $header );
      curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );

      $tweet_id = str_replace( PHP_EOL, '', $this->_id );
      echo "tweet_url: ".$tweet_url."\n";
      $tweet_url = 'https://api.twitter.com/2/tweets?ids='.$tweet_id.'&expansions=attachments.media_keys&media.fields=url';
      curl_setopt( $curl, CURLOPT_URL, $tweet_url);

      // curl を実行し、 JSON 形式に変換
      $curl_result = curl_exec($curl);
      $curl_result_utf8 = utf8_encode($curl_result);
      $json_array = json_decode( $curl_result_utf8, true );
      //$this->PrintApiResult($json_array);

      // 画像の URL と拡張子を取得し、画像のファイル名を作る
      // 画像が複数添付されている場合、['includes']['media'][N]['url'] のNをループすればOK
      $image_url = $json_array['includes']['media']['0']['url'];
      $image_extension = strtolower( pathinfo($image_url)['extension']);
      $image_name = $this->_author.".".$this->_id.".".$image_extension;
      echo "Start to save Image: ", $image_name ,"\n";
      echo "new image name: ", $new_image_name , "\n";

      echo "Download Images:download"."\n";
      // URL から画像をダウンロードする
      $image_data = file_get_contents($image_url."?name=orig");
      file_put_contents( getenv('TWITTER_DOWNLOAD_DIRECTORY').$image_name, $image_data);
      if(!$result)
        echo "Photo Saved: ".$image_name."\n";
      else
        echo "Save Failed: ".$image_name."\n";
      /**/
    }

    function PrintApiResult($array)
    {
      var_dump($array);
    }

    //public function
  }

    function DownloadImageFromTweetId( $tweet_id, $screen_name, $selected_images, $curl)
    {
        // curl に URL を設定する
        $tweet_id = str_replace( PHP_EOL, '', $tweet_id );
        //$tweet_url = 'https://api.twitter.com/1.1/statuses/show.json?id='.$tweet_id;
        $tweet_url = 'https://api.twitter.com/2/tweets/'.$tweet_id.'?expansions=attachments.media_keys&media.fields=url';
        curl_setopt( $curl, CURLOPT_URL, $tweet_url);

        // curl を実行し、 JSON 形式に変換
        $curl_result = curl_exec($curl);
        $curl_result_utf8 = utf8_encode($curl_result);
        $json_array = json_decode( $curl_result_utf8, true );

        var_dump($curl_result);

        //test: ユーザ情報を取得する
        /*
        echo "user info\n";
        $tweet_url = 'https://api.twitter.com/2/tweets/'.$tweet_id.'&expansions=author_id&tweet.fields=created_at&user.fields=username,verified';
        curl_setopt( $curl, CURLOPT_URL, $tweet_url);
        $curl_result = curl_exec($curl);
        $curl_result_utf8 = utf8_encode($curl_result);
        $json_array = json_decode( $curl_result_utf8, true );

        var_dump($curl_result);*/


        if(isset($json_array['errors']))
        {
            echo "Error(".$json_array['errors'][0]['code'].") ".$json_array['errors'][0]['message']."\n";
            echo "\n";
            return;
        }

        //if(!isset($json_array['extended_entities']['media']))
        if(!isset($json_array['includes']['media']))
        {
            echo "Unsupported JSON Type. json['extended_entities']['media'] is not exist.\n";
        }

        // 添付メディアが画像の場合
        //if($json_array['extended_entities']['media']['0']['type']==='photo')
        if($json_array['includes']['media']['0']['type']==='photo')
        {
            $i = 1;
            if( count($json_array['includes']['media']) >= 2 )
            {
              foreach($image_url = $json_array['includes']['media'] as $photo_i )
              {
                  if( empty($selected_images) || in_array( $i, $selected_images ) )
                  {
                    // 画像の URL と拡張子を取得し、画像のファイル名を作る
                    $image_url = $photo_i['media_url_https'];
                    $image_extension = strtolower( pathinfo($image_url)['extension']);
                    $image_name = $json_array['user']['screen_name'].".".$json_array['id'].".".$i.".".$image_extension;
                    echo "Start to save Image: ", $image_name ,"\n";

                    $image_data = file_get_contents($image_url."?name=orig");
                    $result = file_put_contents( getenv('TWITTER_DOWNLOAD_DIRECTORY').$image_name, $image_data);
                    if(!$result)
                      echo "Photo Saved: ".$image_name."\n";
                    else
                      echo "Save Failed: ".$image_name."\n";
                  }

                  $i++;
              }
            }
            else
            {
              // 画像の URL と拡張子を取得し、画像のファイル名を作る
              $image_url = $json_array['includes']['media']['0']['url'];
              $image_extension = strtolower( pathinfo($image_url)['extension']);
              $image_name = $screen_name.".".$tweet_id.".".$image_extension;
              echo "Start to save Image: ", $image_name ,"\n";

              $image_data = file_get_contents($image_url."?name=orig");
              file_put_contents( getenv('TWITTER_DOWNLOAD_DIRECTORY').$image_name, $image_data);
              if(!$result)
                echo "Photo Saved: ".$image_name."\n";
              else
                echo "Save Failed: ".$image_name."\n";
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

    // ここから
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

    $screen_name;
    $tweets = [];
    $tweet_ids = [];
    $selected_images = [];
    $i = 0;
    $tweet_i = 0;
    // 行が読み込まれる間繰り返し
    while( $tweet_url = fgets($file_handle) )
    {
      //$tweet = new Tweet($tweet_id);
      //$tweets[] = new  Tweet();
      $tweet = new Tweet();

        // URL から ツイートのIDを取得
        $tweet->GetTweetIdByRegEx($tweet_url);

        $tweet->GetSelectedImagesByRegEx($tweet_url);

        $tweet->GetUsernameByAPI();

        // URL から選択された画像の番号を取得
        //preg_match_all('/(?<=,)\d+/', $tweet_url, $match);
        //$selected_images = array_map( 'utf8_encode', $match[0]);

        $i++;
        // これから処理する URL を "読み込んだURLの番号: URL" の形式で表示
        echo $i, ": ", str_replace( array("\r\n", "\r", "\n"), '', $tweet_url), "\n";
        $tweet->DownloadImage();

        //DownloadImageFromTweetId( $tweet_id, $screen_name, $selected_images, $curl);
    }

    curl_close($curl);
    fclose($file_handle);
?>
