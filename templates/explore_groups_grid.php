<?php
/**
 * Explore groups grid partial.
 * Expects: $groups (array), $user (array|null), $oob (bool, default false)
 */
declare(strict_types=1);
$oob = $oob ?? false;
?>
<div id="explore-groups" class="explore-groups" <?= $oob ? 'hx-swap-oob="true"' : '' ?>>
    <?php if (empty($groups)): ?>
        <p class="explore-groups__empty">No groups in this city yet. <a href="/?page=group_create">Be the first to start one!</a></p>
    <?php else: ?>
        <div class="group-grid">
            <?php foreach ($groups as $group): ?>
                <?php
                $eventLabel = null;
                if (!empty($group['next_event_date'])) {
                    $today = date('Y-m-d');
                    if ($group['next_event_date'] === $today) {
                        $eventLabel = 'Event today';
                    } else {
                        $days = (int) max(1, round((strtotime($group['next_event_date']) - strtotime($today)) / 86400));
                        $eventLabel = 'Event in ' . $days . ' ' . ($days === 1 ? 'day' : 'days');
                    }
                }
                ?>
                <article class="group-card">
                    <div class="group-card__cover" style="background: <?= group_color((int) $group['id']) ?>;" aria-hidden="true"></div>
                    <div class="group-card__body">
                        <h3><?= e($group['name']) ?></h3>
                        <p class="group-card__meta">
                            <?= (int) $group['member_count'] ?> <?= (int) $group['member_count'] === 1 ? 'member' : 'members' ?>
                            <?php if ($eventLabel !== null): ?>
                                &middot; <span class="group-card__event-badge"><?= e($eventLabel) ?></span>
                            <?php endif; ?>
                        </p>
                        <div id="group-actions-<?= (int) $group['id'] ?>" class="group-card__actions">
                            <?php if ($user && (bool) $group['is_member']): ?>
                                <a href="/?page=group_view&id=<?= (int) $group['id'] ?>" class="btn btn--ghost btn--sm">View group</a>
                            <?php elseif ($user): ?>
                                <form method="post" action="/?page=group_join"
                                      hx-post="/?page=group_join"
                                      hx-target="#group-actions-<?= (int) $group['id'] ?>"
                                      hx-swap="outerHTML">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="group_id" value="<?= (int) $group['id'] ?>">
                                    <button type="submit" class="btn btn--primary btn--sm">Join</button>
                                </form>
                            <?php else: ?>
                                <a href="/?page=signup&next=<?= urlencode('/?page=group_view&id=' . (int) $group['id']) ?>" class="btn btn--primary btn--sm">Join</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
