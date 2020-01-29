<div class="field">

  <?php if (isset($sidebar)): ?>
    <h4><?php echo __('Related subjects') ?></h4>
  <?php elseif (isset($mods)): ?>
    <h3><?php echo __('Subjects') ?></h3>
  <?php else: ?>
    <h3><?php echo __('Subject access points') ?></h3>
  <?php endif; ?>

  <div>
    <ul>
      <?php foreach ($resource->getSubjectAccessPoints() as $item): ?>
        <li>
          <?php echo link_to($item->term->__toString(), array($item->term, 'module' => 'term')) ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>

</div>
