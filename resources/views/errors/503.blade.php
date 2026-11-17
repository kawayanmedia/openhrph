
<!DOCTYPE html>
<html lang="en" class="light scroll-smooth" dir="ltr">
    <head>
        <meta charset="UTF-8">
        <title>See you soon!</title>
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <meta name="version" content="2.1.0">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">

        <!-- Css -->
        <!-- Main Css -->
        <link href="{{ env('SAHOD_APP_SITE', 'https://sahodapp.com') }}/assets/libs/@iconscout/unicons/css/line.css" type="text/css" rel="stylesheet">
        <link href="{{ env('SAHOD_APP_SITE', 'https://sahodapp.com') }}/assets/libs/@mdi/font/css/materialdesignicons.min.css" rel="stylesheet" type="text/css">
        <link rel="stylesheet" href="{{ env('SAHOD_APP_SITE', 'https://sahodapp.com') }}/assets/css/tailwind.css">

    </head>

    <body class="font-nunito text-base text-black dark:text-white dark:bg-slate-900">        
        <section class="relative h-screen flex items-center justify-center text-center bg-[url('../../assets/images/utility.jpg')] bg-no-repeat bg-center bg-cover">
            <div class="absolute inset-0 bg-black/25"></div>
            <div class="absolute inset-0 bg-gradient-to-b from-transparent via-black/40 to-black"></div>
            <div class="container relative">
                <div class="grid grid-cols-1">
                    <h1 class="text-white mb-6 mt-8 md:text-5xl text-3xl font-bold">See you Soon...</h1>
                    <p class="text-white/70 text-lg max-w-xl mx-auto">We're striving to enhance the Sahod app significantly.</p>
                </div><!--end grid-->

                <!-- <div class="grid grid-cols-1 mt-10">
                    <div class="text-center">
                        <span id="maintenance" class="timer text-white text-[56px] tracking-[1px]"></span>
                        <span class="block text-base font-semibold uppercase text-white">Minutes</span>
                    </div>
                </div> -->
                <!--end grid-->

                <div class="grid grid-cols-1 mt-6">
                    <div class="text-center subcribe-form">
                        <!-- <form class="relative mx-auto max-w-xl">
                            <input type="email" id="subemail" name="name" class="py-4 pe-40 ps-6 w-full h-[50px] outline-none text-black dark:text-white rounded-full bg-white/70 dark:bg-slate-900/70 border border-gray-100 dark:border-gray-700" placeholder="Enter your email id..">
                            <button type="submit" class="py-2 px-5 inline-block font-semibold tracking-wide align-middle duration-500 text-base text-center absolute top-[2px] end-[3px] h-[46px] bg-indigo-600 hover:bg-indigo-700 border border-indigo-600 hover:border-indigo-700 text-white rounded-full">Subcribe Now</button>
                        </form> -->
                        <!--end form-->
                    </div>
                </div><!--end grid-->
            </div><!--end container-->
        </section><!--end section-->  
        
        <!-- JAVASCRIPTS -->
        <script src="{{ env('SAHOD_APP_SITE', 'https://sahodapp.com') }}/assets/libs/feather-icons/feather.min.js"></script>
        <script src="{{ env('SAHOD_APP_SITE', 'https://sahodapp.com') }}/assets/js/plugins.init.js"></script>
        <script src="{{ env('SAHOD_APP_SITE', 'https://sahodapp.com') }}/assets/js/app.js"></script>
        <!-- JAVASCRIPTS -->
    </body>
</html>