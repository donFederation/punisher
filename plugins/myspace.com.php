<?php


function preParse($input, $type)
{

    switch ($type) {

        // Apply changes to HTML documents
        case 'html':

            // Javascript fix - break up the string into 2 pieces so we don't
            // confuse the main proxy parser with a ".innerHTML = " string.
            $input = str_replace('"invalidLogin.innerHTML = \""', '"invalidLogin.in"+"nerHTML = \""', $input);

            // Reroute AJAX requests
            $insert = <<<OUT
				<script type="text/javascript">
				XMLHttpRequest.prototype.open = function(method,uri,async) {
					return this.base_open(method, parseURL(uri.replace('localhost', 'www.myspace.com'), 'ajax'), async);
				};
				</script>
OUT;
            $input = str_replace('</head>', $insert . '</head>', $input);

            break;

    }

    // Return changed
    return $input;

}
