<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Továbblépés előtt..</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" rel="stylesheet">
    
    {if $recaptcha_required}
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    {/if}

    <style>
        .container {
            width: fit-content;
            margin: auto;
            background-color: #4981ff;
            padding: 50px;
            margin-top: 50px;
            border-radius: 20px;
            box-shadow: 5px 5px 20px 0px #4981ff;
        }

        footer{
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 50px;
            background: #394259;
        }

        footer div {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 50px;
            width: 100%;
        }

        a{
            color: white;
            font-weight: bold;
        }

        a:hover{
            color: #ccc;
            text-decoration: none;
        }

        ul{
            margin: 0;
        }
        
        li {
            display: inline;
            margin-right: 10px;
        }

        li:last-child {
            margin: 0;
        }


    </style>
</head>
<body>
    <div class="container">
        {if $password}
            <h2>Ehhez a linkhez jelszó szükséges</h2>
        {else}
            <h2>Erősítsd meg hogy nem vagy robot!</h2>
        {/if}

        <form id="the-form">
            {if $password}
                <div class="form-group mb-2">
                    <label for="password">Jelszó:</label>
                    <input type="text" class="form-control" id="password" name="password" required>
                </div>
            {/if}

            {if $recaptcha_required}
                <div class="g-recaptcha" data-sitekey="{$public_key}"></div>
            {/if}

            <button type="submit" class="btn btn-light mt-2">Tovább</button>
        </form>
    </div>

    <footer>
        <div>
            <ul>
                <li><a href="/">Create new url</a></li>
                <li><a href="/p">Piracy</a></li>
            </ul>
            
            
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        var contactForm = $("#the-form");
        contactForm.on("submit", function(e) {
            e.preventDefault();

            {if $recaptcha_required}
                {literal}
                if(grecaptcha.getResponse() == ""){
                    alert("Erősítsd meg hogy nem vagy robot");
                    return;
                }
                {/literal}
            {/if}

            var short = '{$short_url}';
            {if $password}
                var password = $("#password").val();
            {/if}

            $.ajax({
                type: "POST",
                url: "/get-short-gate",
                data: {
                    short: short,
                    {if $password}
                        password: password,
                    {/if}
                    {if $recaptcha_required}
                        captcha: grecaptcha.getResponse()
                    {/if}
                },
                success: function(response) {
                    if(response.success){
                        window.location.replace(response.response);
                    } else {
                        alert("ERROR: " + response.reason);
                        {if $recaptcha_required}
                            grecaptcha.reset()
                        {/if}
                    }
                }
            })
        });
    </script>
</body>
</html>
