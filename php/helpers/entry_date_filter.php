<?php

function getSelectedEntryDate(): string
{
    $rawDate = $_GET['entry_date'] ?? '';
    if (!is_string($rawDate) || $rawDate === '') {
        return date('Y-m-d');
    }

    $selectedDate = DateTime::createFromFormat('Y-m-d', $rawDate);
    if ($selectedDate instanceof DateTime && $selectedDate->format('Y-m-d') === $rawDate) {
        return $rawDate;
    }

    return date('Y-m-d');
}

function renderEntryDateFilter(string $selectedDate): void
{
    $action = htmlspecialchars($_SERVER['PHP_SELF'] ?? '', ENT_QUOTES, 'UTF-8');
    ?>
    <form method="get" action="<?php echo $action; ?>" class="row g-2 align-items-end mb-3">
        <?php foreach ($_GET as $key => $value): ?>
            <?php if ($key === 'entry_date' || is_array($value)): ?>
                <?php continue; ?>
            <?php endif; ?>
            <input
                type="hidden"
                name="<?php echo htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8'); ?>"
                value="<?php echo htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?>"
            >
        <?php endforeach; ?>
        <div class="col-sm-6 col-md-4 col-lg-3">
            <label for="entry_date" class="form-label mb-1">Filter Date</label>
            <input
                type="date"
                class="form-control"
                id="entry_date"
                name="entry_date"
                value="<?php echo htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8'); ?>"
            >
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary">View</button>
        </div>
        <div class="col-auto">
            <a href="<?php echo $action; ?>" class="btn btn-outline-secondary">Today</a>
        </div>
    </form>
    <?php
}
