<?php declare(strict_types=1); ?>
<div class="form-group <?= !empty($errors['city_id']) ? 'form-group--error' : '' ?>">
    <label>City</label>

    <?php if ($preselected): ?>
        <!-- Selected state: city already chosen -->
        <div id="city-widget" class="city-widget city-widget--has-value">
            <input type="hidden" name="city_id" value="<?= (int) $preselected['id'] ?>">
            <div class="city-widget__chosen">
                <span><?= e($preselected['name']) ?>, <?= e($preselected['state']) ?></span>
                <button type="button" class="btn btn--ghost btn--sm"
                        hx-get="/?page=city_search&reset=1"
                        hx-target="#city-widget"
                        hx-swap="outerHTML">
                    Change
                </button>
            </div>
        </div>
    <?php else: ?>
        <!-- Search state: no city selected yet -->
        <div id="city-widget" class="city-widget">
            <input
                type="text"
                class="city-widget__input"
                name="city_q"
                placeholder="Search cities…"
                autocomplete="off"
                hx-get="/?page=city_search"
                hx-trigger="input changed delay:300ms"
                hx-target="#city-results"
                hx-swap="outerHTML"
                aria-label="Search for a city"
                aria-autocomplete="list"
                aria-controls="city-results"
            >
            <input type="hidden" name="city_id" value="">
            <div id="city-results" class="city-widget__results" role="listbox"></div>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors['city_id'])): ?>
        <span class="form-error" id="city-error"><?= e($errors['city_id']) ?></span>
    <?php endif; ?>

    <!-- Progressive fallback: only rendered when JavaScript is disabled.
         The <style> inside <noscript> hides the HTMX widget above. -->
    <noscript>
        <style>#city-widget { display: none !important; }</style>
        <select name="city_id" <?= !empty($errors['city_id']) ? 'aria-describedby="city-error"' : '' ?>>
            <option value="">— Select a city —</option>
            <?php foreach ($cities as $city): ?>
                <option
                    value="<?= (int) $city['id'] ?>"
                    <?= (!empty($old['city_id']) && $old['city_id'] === (string) $city['id']) ? 'selected' : '' ?>
                ><?= e($city['name']) ?>, <?= e($city['state']) ?></option>
            <?php endforeach; ?>
        </select>
    </noscript>
</div>
