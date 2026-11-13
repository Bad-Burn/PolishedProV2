<?php
/**
 * The one email template. Every message the app sends is a heading, a few
 * short paragraphs and at most one button — the same shape as an in-app
 * notification — so one carefully tested layout covers all of them.
 *
 * Email clients ignore <style> blocks and external CSS unevenly, so styles are
 * inline and the layout is a table. Keep it that way.
 *
 * @var string $appName
 * @var string $greeting
 * @var string $heading
 * @var array<int, string> $paragraphs plain text, escaped here
 * @var string|null $actionUrl
 * @var string|null $actionLabel
 * @var string $footer
 */

declare(strict_types=1);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title><?= e($heading) ?></title>
</head>
<body style="margin:0;padding:0;background:#fbf8f5;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#fbf8f5;">
    <tr>
        <td align="center" style="padding:32px 16px;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:560px;">
                <tr>
                    <td style="padding:0 4px 20px;font:600 20px/1.2 Georgia,'Times New Roman',serif;color:#7a2740;">
                        <?= e($appName) ?>
                    </td>
                </tr>
                <tr>
                    <td style="background:#ffffff;border:1px solid #e7ded5;border-radius:12px;padding:32px;font:16px/1.6 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1a1614;">
                        <p style="margin:0 0 8px;color:#4a423d;"><?= e($greeting) ?></p>
                        <h1 style="margin:0 0 16px;font:600 22px/1.3 Georgia,'Times New Roman',serif;color:#1a1614;"><?= e($heading) ?></h1>

                        <?php foreach ($paragraphs as $paragraph): ?>
                            <p style="margin:0 0 16px;"><?= e($paragraph) ?></p>
                        <?php endforeach; ?>

                        <?php if (!empty($actionUrl)): ?>
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:24px 0 8px;">
                                <tr>
                                    <td style="border-radius:8px;background:#7a2740;">
                                        <a href="<?= e($actionUrl) ?>"
                                           style="display:inline-block;padding:12px 22px;font:600 15px/1 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#ffffff;text-decoration:none;border-radius:8px;">
                                            <?= e($actionLabel ?? 'Open PolishedPro') ?>
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:16px 0 0;font-size:13px;color:#7a6f68;word-break:break-all;">
                                Or paste this link into your browser: <?= e($actionUrl) ?>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 4px 0;font:13px/1.5 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#7a6f68;">
                        <?= e($footer) ?>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
