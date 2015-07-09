<?php
function smarty_modifier_url2a($content)
{
	if( preg_match_all('#(http(s)?://([\w-]+\.)+[\w-]+(/[\w-./?%&=]*)?)#', $content, $match) ){
		foreach($match[1] as $url){
			$content = str_replace($url, '<A href="' . $url . '" target="_blank">' . $url . '</A>', $content);
		}
	}

	return $content;
}