<ul style="list-style:none;">
<?php foreach($rows as $row): ?>
<?php $name_parts = explode(' ', $row->name); ?>
    <li>
        <a href="/hopo/<?php echo $row->id; ?>">
            <img src="<?php echo $row->aw_thumb; ?>" alt="<?php echo $row->name; ?>" style="padding:5px;float:left;width:50px;" />
            <?php echo "{$name_parts[0]} {$name_parts[1]} {$name_parts[2]}"; ?>...
        </a>
        <div style="margin:0px;padding:0px;">&pound;<?php echo $row->price; ?></div>
        <div style="clear:both;"></div>
    </li>
<?php endforeach; ?>
</ul>
