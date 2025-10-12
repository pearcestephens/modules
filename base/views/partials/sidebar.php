<?php
/**
 * Template Partial: Sidebar Navigation
 * 
 * Contains the main navigation menu based on user permissions
 * 
 * Required variables:
 *   - $organisedCats: Array of navigation categories with items
 * 
 * @package Modules\Base\Views\Layouts
 */
?>
<div class="sidebar">
  <nav class="sidebar-nav">
    <ul class="nav">
    <li class="nav-item open">
        <a class="nav-link active" href="/index.php">View Dashboard</a>
    </li>
    <?php 
      foreach($organisedCats as $c){
        if (count($c->itemsArray) > 0){
          echo '<li class="nav-title">'.$c->title.'</li>';
          foreach($c->itemsArray as $i){
            echo '<li class="nav-item">
                      <a class="nav-link" href="'.$i->filename.'">'.$i->name.'</a>
                  </li>';
          }
        }
      }
    ?>
    </ul>
  </nav>
</div>
