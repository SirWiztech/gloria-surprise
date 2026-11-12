<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$csrf = csrf_token();
$cssVersion = @filemtime(__DIR__ . '/assets/css/style.css') ?: time();
$jsVersion = @filemtime(__DIR__ . '/assets/js/app.js') ?: time();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#D90429">
    <meta name="robots" content="noindex, nofollow, noarchive, noimageindex">
    <meta name="color-scheme" content="light">
    <title>A question for Gloria 💗</title>

    <link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500;1,600&family=Poppins:wght@300;400;500;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= e((string) $cssVersion) ?>">
</head>

<body data-csrf="<?= e($csrf) ?>">
    <div class="background" aria-hidden="true">
        <span class="orb orb-one"></span>
        <span class="orb orb-two"></span>
        <span class="orb orb-three"></span>
        <div class="hearts" id="bg-hearts"></div>
    </div>

    <noscript>
        <div class="noscript">Please turn on JavaScript to open this little surprise 💗</div>
    </noscript>

    <main class="stage">
        <div class="shell">
            <header class="progress" id="progress" hidden>
                <div class="progress-meta">
                    <span class="progress-label" id="progress-label">1 of 6</span>
                    <span class="progress-note" aria-hidden="true">♥</span>
                </div>
                <div
                    class="progress-track"
                    id="progress-track"
                    role="progressbar"
                    aria-labelledby="progress-label"
                    aria-valuemin="0"
                    aria-valuemax="6"
                    aria-valuenow="0">
                    <div class="progress-fill" id="progress-fill"><span aria-hidden="true">♥</span></div>
                </div>
            </header>

            <div class="views">
                <!-- ── Welcome ─────────────────────────────────────────────── -->
                <section class="view view-welcome is-active" id="view-welcome" data-view="welcome"
                         aria-labelledby="welcome-name">
                    <div class="card welcome-card">
                        <div class="portrait-wrap">
                            <div class="portrait" id="portrait">
                                <span class="portrait-fallback" aria-hidden="true">💗</span>
                                <!-- REPLACE assets/img/chiamaka.jpg WITH HER PHOTO (same filename). -->
                                <img
                                    src="assets/img/chiamaka.jpg"
                                    alt="Gloria"
                                    width="800"
                                    height="1000"
                                    data-photo
                                    decoding="async">
                            </div>
                            <span class="portrait-heart" aria-hidden="true">♥</span>
                        </div>

                        <div class="welcome-copy">
                            <h1 class="her-name" id="welcome-name">Chinweuba Chiamaka Gloria</h1>
                            <p class="tagline">Someone special has a few questions for you 💗</p>
                            <button class="btn btn-primary btn-pulse" type="button" data-action="proceed">
                                Proceed
                            </button>
                        </div>
                    </div>
                </section>

                <!-- ── Step 1 ──────────────────────────────────────────────── -->
                <section class="view" id="view-1" data-view="1" aria-labelledby="q1" hidden>
                    <div class="card">
                        <span class="ornament" aria-hidden="true">♡</span>
                        <h2 class="question" id="q1" tabindex="-1">Do you feel comfortable around Wiztech?</h2>

                        <div class="choices" role="group" aria-labelledby="q1">
                            <button class="btn btn-choice choice-yes" type="button" data-step="1"
                                    data-choice="yes">YES</button>
                            <button class="btn btn-choice choice-no" type="button" data-step="1"
                                    data-choice="no">NO</button>
                        </div>

                        <p class="form-error" id="error-1" role="alert" hidden></p>
                    </div>
                </section>

                <!-- ── Step 2 ──────────────────────────────────────────────── -->
                <section class="view" id="view-2" data-view="2" aria-labelledby="q2" hidden>
                    <div class="card">
                        <span class="ornament" aria-hidden="true">♡</span>
                        <h2 class="question" id="q2" tabindex="-1">What is it that you like about Wiztech?</h2>

                        <form class="step-form" data-form-step="2" novalidate>
                            <label class="sr-only" for="likes">What is it that you like about Wiztech?</label>
                            <textarea id="likes" name="likes" rows="4" minlength="3" maxlength="2000"
                                      placeholder="Take your time, there's no wrong answer…" required></textarea>
                            <p class="form-error" id="error-2" role="alert" hidden></p>
                            <button class="btn btn-primary" type="submit">Next</button>
                        </form>
                    </div>
                </section>

                <!-- ── Step 3 ──────────────────────────────────────────────── -->
                <section class="view" id="view-3" data-view="3" aria-labelledby="q3" hidden>
                    <div class="card">
                        <span class="ornament" aria-hidden="true">♡</span>
                        <h2 class="question" id="q3" tabindex="-1">What is it that you don't like about Wiztech?</h2>
                        <p class="question-note">Be honest, he wants to grow 🙂</p>

                        <form class="step-form" data-form-step="3" novalidate>
                            <label class="sr-only" for="dislikes">What is it that you don't like about Wiztech?</label>
                            <textarea id="dislikes" name="dislikes" rows="4" minlength="3" maxlength="2000"
                                      required></textarea>
                            <p class="form-error" id="error-3" role="alert" hidden></p>
                            <button class="btn btn-primary" type="submit">Next</button>
                        </form>
                    </div>
                </section>

                <!-- ── Step 4 ──────────────────────────────────────────────── -->
                <section class="view" id="view-4" data-view="4" aria-labelledby="q4" hidden>
                    <div class="card">
                        <span class="ornament" aria-hidden="true">♡</span>
                        <h2 class="question" id="q4" tabindex="-1">
                            Do you see yourself having something serious to do with Wiztech in the future?
                        </h2>

                        <div class="choices" role="group" aria-labelledby="q4">
                            <button class="btn btn-choice choice-yes" type="button" data-step="4"
                                    data-choice="yes">YES</button>
                            <button class="btn btn-choice choice-no" type="button" data-step="4"
                                    data-choice="no">NO</button>
                        </div>

                        <p class="form-error" id="error-4" role="alert" hidden></p>
                    </div>
                </section>

                <!-- ── Step 5 ──────────────────────────────────────────────── -->
                <section class="view" id="view-5" data-view="5" aria-labelledby="q5" hidden>
                    <div class="card">
                        <span class="ornament" aria-hidden="true">♡</span>
                        <h2 class="question" id="q5" tabindex="-1">Would you want to build a lovely future with him?</h2>

                        <div class="choices" role="group" aria-labelledby="q5">
                            <button class="btn btn-choice choice-yes" type="button" data-step="5"
                                    data-choice="yes">YES</button>
                            <button class="btn btn-choice choice-no" type="button" data-step="5"
                                    data-choice="no">NO</button>
                        </div>

                        <p class="form-error" id="error-5" role="alert" hidden></p>
                    </div>
                </section>

                <!-- ── Step 6 ──────────────────────────────────────────────── -->
                <section class="view view-final" id="view-6" data-view="6" aria-labelledby="q6" hidden>
                    <div class="card card-final">
                        <span class="ornament ornament-large" aria-hidden="true">♥</span>
                        <h2 class="question question-final" id="q6" tabindex="-1">
                            Would you want to be his Forever? 💗
                        </h2>

                        <div class="choices choices-final choices-three" role="group" aria-labelledby="q6">
                            <button class="btn btn-choice choice-yes choice-yes-final" type="button" data-step="6"
                                    data-choice="yes">YES 💗</button>
                            <button class="btn btn-choice choice-no" type="button" data-step="6"
                                    data-choice="no">NO</button>
                            <button class="btn btn-choice choice-not-ready" type="button" data-step="6"
                                    data-choice="not_ready">NOT READY</button>
                        </div>

                        <p class="form-error" id="error-6" role="alert" hidden></p>
                    </div>
                </section>

                <!-- ── YES ─────────────────────────────────────────────────── -->
                <section class="view view-celebration" id="view-yes" data-view="yes" aria-labelledby="yes-title"
                         hidden>
                    <div class="card celebration-card">
                        <p class="celebration-eyebrow">She said YES 💗</p>
                        <div class="celebration-badge" aria-hidden="true">💗</div>
                        <h2 class="celebration-title" id="yes-title" tabindex="-1">
                            Your response has been received 💗
                        </h2>
                        <p class="celebration-message">
                            Gloria, you just made someone's whole world brighter. Congratulations on one of the best
                            decisions of your life. Wiztech promises to love you, respect you, and keep choosing you
                            every day. 🎊
                        </p>

                        <button class="music-toggle" id="music-toggle" type="button" aria-pressed="false">
                            <span class="music-bars" aria-hidden="true"><i></i><i></i><i></i></span>
                            <span class="music-label">Play soft music</span>
                        </button>
                    </div>
                </section>

                <!-- ── NO ──────────────────────────────────────────────────── -->
                <section class="view view-declined" id="view-no" data-view="no" aria-labelledby="no-message" hidden>
                    <div class="card declined-card">
                        <div class="declined-badge" aria-hidden="true">💗</div>
                        <p class="declined-message" id="no-message" tabindex="-1" role="status">
                            Thank you for your honesty, Gloria. It means a lot that you answered from the heart.
                            Whatever happens, you are deeply respected and admired. 💗
                        </p>
                    </div>
                </section>

                <!-- ── NOT READY ──────────────────────────────────────────── -->
                <section class="view view-declined" id="view-not-ready" data-view="not-ready"
                         aria-labelledby="not-ready-message" hidden>
                    <div class="card declined-card not-ready-card">
                        <div class="declined-badge" aria-hidden="true">🤍</div>
                        <p class="declined-message" id="not-ready-message" tabindex="-1" role="status">
                            Thank you for being honest, Gloria. There is no rush and no pressure — take all the time
                            you need. You are deeply respected and admired. 💗
                        </p>
                    </div>
                </section>
            </div>
        </div>
    </main>

    <div class="rising-hearts" id="rising-hearts" aria-hidden="true"></div>
    <div class="toast" id="toast" role="status" aria-live="polite" hidden></div>

    <audio id="music" loop preload="none">
        <!-- Replace assets/audio/music.wav with your own song if you like. -->
        <source src="assets/audio/music.wav" type="audio/wav">
    </audio>

    <script src="assets/js/vendor/confetti.browser.min.js" defer></script>
    <script src="assets/js/app.js?v=<?= e((string) $jsVersion) ?>" defer></script>
</body>
</html>
