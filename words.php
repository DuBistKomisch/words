<?php
  set_time_limit(0);
  $start_time = microtime(true);
  
  // define rules - letter and word multipliers
  $rules = isset($_POST['rules']) ? $_POST['rules'] : 'wwf';
  include($rules . '.rules');
  
  function generate_tile_select ($name, $blank = false) {
    print '<select id="' . $name . '" name="' . $name . '">';
    print '<option value="_"></option>';
    for ($tile = 0; $tile < 26; $tile++) {
      $char = chr(ord('A')+$tile);
      print '<option value="' . $char . '"' . (isset($_POST[$name]) && $_POST[$name] == $char ? ' selected="selected"' : '') . '>' . $char . '</option>';
    }
    if ($blank)
      print '<option value="*"' . (isset($_POST[$name]) && $_POST[$name] == '*' ? ' selected="selected"' : '') . '>*</option>';
    print "</select>\n";
  }
  
  function get_board_colour ($row, $col) {
    global $ls, $ws;
    $prefix = ' style="background-color:';
    $suffix = ';"';
    if ($row == 7 && $col == 7)
      return $prefix . 'purple' . $suffix;
    if ($ls[$row][$col] == 3)
      return $prefix . 'green' . $suffix;
    if ($ls[$row][$col] == 2)
      return $prefix . 'blue' . $suffix;
    if ($ws[$row][$col] == 3)
      return $prefix . 'orange' . $suffix;
    if ($ws[$row][$col] == 2)
      return $prefix . 'red' . $suffix;
    return '';
  }
  
  if (isset($_POST['submit'])) {
    // load dictionary
    include($_POST['dict'] . '.dict');
    
    // which board locations are taken
    $istaken = array();
    $isinit = true;
    for ($row = 0; $row < 15; $row++) {
      $istaken[$row] = array();
      for ($col = 0; $col < 15; $col++)
        if ($_POST['board_' . $row . '_' . $col] == '_')
          $istaken[$row][$col] = false;
        else {
          $istaken[$row][$col] = true;
          $isinit = false;
        }
    }
    
    // wrapper function to handle out-of-board co-ords elegantly
    function is_taken($row, $col) {
      global $istaken;
      if ($row < 0 || $row >= 15 || $col < 0 || $col >= 15)
        return false;
      return $istaken[$row][$col];
    }
    
    // generate ALL permutations of hand tiles
    $hand = array();
    $sofar = array();
    $blank = array();
    $used = array();
    $perms = array(null);
    for ($i = 0; $i < 7; $i++)
      if (isset($_POST['hand_' . $i]) && $_POST['hand_' . $i] != '_') {
        array_push($hand, $_POST['hand_' . $i]);
        array_push($used, false);
        $perms[] = array();
      }
    $hand_count = count($hand);
    function add_hand_tile ($depth) {
      global $hand, $sofar, $blank, $used, $perms, $hand_count;
      if ($depth > 0)
        $perms[$depth][implode($sofar)] = implode($blank);
      if ($depth == $hand_count)
        return;
      for ($i = 0; $i < $hand_count; $i++) {
        if ($used[$i])
          continue;
        $used[$i] = true;
        if ($hand[$i] == '*') {
          array_push($blank, '*');
          for ($tile = 0; $tile < 26; $tile++) {
            array_push($sofar, chr(ord('A')+$tile));
            add_hand_tile($depth + 1);
            array_pop($sofar);
          }
          array_pop($blank);
        } else {
          array_push($sofar, $hand[$i]);
          array_push($blank, ' ');
          add_hand_tile($depth + 1);
          array_pop($blank);
          array_pop($sofar);
        }
        $used[$i] = false;
      }
    }
    add_hand_tile(0);
    
    $position_count = 0; $attempt_count = 0;
    $solutions = array();
    // for each possible starting square
    for ($row = 0; $row < 15; $row++) {
      for ($col = 0; $col < 15; $col++) {
        if (is_taken($row, $col))
          continue;
        // try along rows
        $cols = array($col);
        for ($i = $col + 1; $i < 15 && count($cols) < 7; $i++)
          if (!is_taken($row, $i))
            array_push($cols, $i);
        for ($i = 1; $i <= count($cols) && $i <= $hand_count; $i++) {
          $position_count++;
          if ($cols[$i-1] - $cols[0] < $i && !is_taken($row, $cols[0]-1) && !is_taken($row, $cols[$i-1]+1) && !($isinit && $row == 7 && $cols[0] <= 7 && $cols[$i-1] >= 7))
            continue;
          for ($start = $cols[0] - 1; $start >= 0 && is_taken($row, $start); $start--); $start++;
          for ($end = $cols[$i-1] + 1; $end < 15 && is_taken($row, $end); $end++); $end--;
          $source = array();
          for ($j = $start; $j <= $end; $j++)
            $source[$j] = is_taken($row, $j) ? -1 : array_search($j, $cols);
          foreach ($perms[$i] as $fromhand => $blank) {
            $word = '';
            $attempt_count++;
            foreach ($source as $tile => $from)
              $word .= $from == -1 ? $_POST['board_' . $row . '_' . $tile] : $fromhand{$from};
            if (!array_key_exists($word, $isword))
              continue;
            foreach ($source as $tile => $from)
              if ($from != -1) {
                for ($j = $row; $j >= 0 && (is_taken($j, $tile) || $j == $row); $j--); $j++;
                for ($side = ''; $j < 15 && (is_taken($j, $tile) || $j == $row); $j++)
                  $side .= $j == $row ? $fromhand{$from} : $_POST['board_' . $j . '_' . $tile];
                if (strlen($side) > 1 && !array_key_exists($side, $isword))
                  continue 2;
              }
            $html = '';
            $score = 0;
            $subscore = 0;
            $multiplier = 1;
            foreach ($source as $tile => $from) {
              if ($from == -1) {
                $char = $_POST['board_' . $row . '_' . $tile];
                $html .= '<b>' . $char . '</b>';
                $score += $value[$char];
              } else {
                $char = $fromhand{$from};
                if ($blank{$from} == ' ') {
                  $html .= $char;
                  $score += $value[$char] * $ls[$row][$tile];
                  $multiplier *= $ws[$row][$tile];
                } else {
                  $html .= '<u>' . $char . '</u>';
                }
                $side_count = 0;
                $subsubscore = 0;
                for ($j = $row; $j >= 0 && (is_taken($j, $tile) || $j == $row); $j--); $j++;
                for ($side = ''; $j < 15 && (is_taken($j, $tile) || $j == $row); $j++) {
                  $subsubscore += $j == $row ? $value[$char] * $ls[$row][$tile] : $value[$_POST['board_' . $j . '_' . $tile]];
                  $side_count++;
                }
                $subsubscore *= $ws[$row][$tile];
                if ($side_count > 1)
                  $subscore += $subsubscore;
              }
            }
            $score *= $multiplier;
            if ($i == 7)
              $score += $bonus;
            $score += $subscore;
            array_push($solutions, array(($row+1) . chr(ord('A')+$start) . ' &rarr; ' . $html . ($i == 7 ? '!' : '') . ' ' . $score, $score, $word, $row, $start, true));
          }
        }
        // try down columns
        $rows = array($row);
        for ($i = $row + 1; $i < 15 && count($rows) < 7; $i++)
          if (!is_taken($i, $col))
            array_push($rows, $i);
        for ($i = 1; $i <= count($rows) && $i <= $hand_count; $i++) {
          $position_count++;
          if ($rows[$i-1] - $rows[0] < $i && !is_taken($rows[0]-1, $col) && !is_taken($rows[$i-1]+1, $col) && !($isinit && $col == 7 && $rows[0] <= 7 && $rows[$i-1] >= 7))
            continue;
          for ($start = $rows[0] - 1; $start >= 0 && is_taken($start, $col); $start--); $start++;
          for ($end = $rows[$i-1] + 1; $end < 15 && is_taken($end, $col); $end++); $end--;
          $source = array();
          for ($j = $start; $j <= $end; $j++)
            $source[$j] = is_taken($j, $col) ? -1 : array_search($j, $rows);
          foreach ($perms[$i] as $fromhand => $blank) {
            $word = '';
            $attempt_count++;
            foreach ($source as $tile => $from)
              $word .= $from == -1 ? $_POST['board_' . $tile . '_' . $col] : $fromhand{$from};
            if (!array_key_exists($word, $isword))
              continue;
            foreach ($source as $tile => $from)
              if ($from != -1) {
                for ($j = $col; $j >= 0 && (is_taken($tile, $j) || $j == $col); $j--); $j++;
                for ($side = ''; $j < 15 && (is_taken($tile, $j) || $j == $col); $j++)
                  $side .= $j == $col ? $fromhand{$from} : $_POST['board_' . $tile . '_' . $j];
                if (strlen($side) > 1 && !array_key_exists($side, $isword))
                  continue 2;
              }
            $html = '';
            $score = 0;
            $subscore = 0;
            $multiplier = 1;
            foreach ($source as $tile => $from) {
              if ($from == -1) {
                $char = $_POST['board_' . $tile . '_' . $col];
                $html .= '<b>' . $char . '</b>';
                $score += $value[$char];
              } else {
                $char = $fromhand{$from};
                if ($blank{$from} == ' ') {
                  $html .= $char;
                  $score += $value[$char] * $ls[$tile][$col];
                  $multiplier *= $ws[$tile][$col];
                } else {
                  $html .= '<u>' . $char . '</u>';
                }
                $side_count = 0;
                $subsubscore = 0;
                for ($j = $col; $j >= 0 && (is_taken($tile, $j) || $j == $col); $j--); $j++;
                for ($side = ''; $j < 15 && (is_taken($tile, $j) || $j == $col); $j++) {
                  $subsubscore += $j == $col ? $value[$char] * $ls[$tile][$col] : $value[$_POST['board_' . $tile . '_' . $j]];
                  $side_count++;
                }
                $subsubscore *= $ws[$tile][$col];
                if ($side_count > 1)
                  $subscore += $subsubscore;
              }
            }
            $score *= $multiplier;
            if ($i == 7)
              $score += 35;
            $score += $subscore;
            array_push($solutions, array(chr(ord('A')+$col) . ($start+1) . ' &darr; ' . $html . ($i == 7 ? '!' : '') . ' ' . $score, $score, $word, $start, $col, false));
          }
        }
      }
    }
    
    // sort solutions
    function sort_score ($s1, $s2) {
      // sorts by score [1] descending
      if ($s1[1] < $s2[1])
        return 1;
      if ($s1[1] > $s2[1])
        return -1;
      return 0;
    }
    function sort_length ($s1, $s2) {
      // sorts by length [2] ascending
      if (strlen($s1[2]) < strlen($s2[2]))
        return -1;
      if (strlen($s1[2]) > strlen($s2[2]))
        return 1;
      // then score [1] descending
      if ($s1[1] < $s2[1])
        return 1;
      if ($s1[1] > $s2[1])
        return -1;
      return 0;
    }
    usort($solutions, 'sort_' . $_POST['sort']);
  }
?>
<html>
  <head>
    <title>Move Finder &ndash; Words With Friends</title>
  </head>
  <body>
    <h3>Board &amp; Hand</h3>
    <form id="form" action="words.php" method="post">
      <table>
        <tr>
          <td></td>
          <?php for ($col = 0; $col < 15; $col++): ?>
          <td style="text-align:center;"><?php echo chr(ord('A')+$col); ?></td>
          <?php endfor; ?>
        </tr>
        <?php for ($row = 0; $row < 15; $row++): ?>
        <tr>
          <td style="text-align:right;"><?php echo $row+1; ?></td>
          <?php for ($col = 0; $col < 15; $col++): ?>
          <td<?php echo get_board_colour($row, $col); ?>>
            <?php generate_tile_select('board_' . $row . '_' . $col); ?>
          </td>
          <?php endfor; ?>
        </tr>
        <?php endfor; ?>
        <tr>
          <td></td>
          <td colspan="3"><input type="reset"></td>
          <td style="text-align:right;">[</td>
          <?php for ($i = 0; $i < 7; $i++): ?>
          <td>
            <?php generate_tile_select('hand_' . $i, true); ?>
          </td>
          <?php endfor; ?>
          <td>]</td>
          <td colspan="3" style="text-align:right;"><input type="submit" name="submit" value="Search" /></td>
        </tr>
      </table>
      <table>
        <tr>
          <td><label for="rules">Board & Scoring:</label></td>
          <td colspan="3"><select name="rules">
            <option value="wwf"<?php if (isset($_POST['rules']) && $_POST['rules'] == 'wwf') echo ' selected="selected"'?>>Words With Friends</option>
            <option value="scrabble"<?php if (isset($_POST['rules']) && $_POST['rules'] == 'scrabble') echo ' selected="selected"'?>>Scrabble</option>
          </select></td>
        </tr>
        <tr>
          <td><label for="dict">Dictionary:</label></td>
          <td colspan="3"><select name="dict">
            <option value="wwf"<?php if (isset($_POST['dict']) && $_POST['dict'] == 'wwf') echo ' selected="selected"'?>>Words With Friends</option>
            <option value="standard"<?php if (isset($_POST['dict']) && $_POST['dict'] == 'standard') echo ' selected="selected"'?>>Standard (North American)</option>
            <option value="sowpods"<?php if (isset($_POST['dict']) && $_POST['dict'] == 'sowpods') echo ' selected="selected"'?>>SOWPODS (International)</option>
          </select></td>
        </tr>
        <tr>
          <td><label for="sort">Sorting Order:</label></td>
          <td colspan="3"><select name="sort">
            <option value="score"<?php if (isset($_POST['sort']) && $_POST['sort'] == 'score') echo ' selected="selected"'?>>Highest Score</option>
            <option value="length"<?php if (isset($_POST['sort']) && $_POST['sort'] == 'length') echo ' selected="selected"'?>>Shortest Words</option></select>
          </td>
        </tr>
        <tr>
          <td><label for="game">Saved Game:</label></td>
          <td><select id="game" name="game">
            <option value="">[new]</option>
            <?php
              foreach ($_COOKIE as $cookie => $value):
                $parts = explode('_', $cookie);
                if ($parts[0] == 'words'):
            ?>
            <option value="<?php echo $cookie; ?>"<?php if (isset($_POST['game']) && $_POST['game'] == $cookie) echo ' selected="selected"'; ?>><?php echo $parts[1]; ?></option>
            <?php endif; endforeach; ?>
          </td>
          <td><a href="javascript:void(0);" onclick="javascript:savegame();">save</a></td>
          <td><a href="javascript:void(0);" onclick="javascript:loadgame();">load</a></td>
        </tr>
      </table>
    </form>
    <script type="text/javascript">
      function doword (word, row, column, horizontal) {
        for (offset = 0; offset < word.length; offset++)
          document.getElementById('board_' + (row + (horizontal ? 0 : offset)) + '_' + (column + (horizontal ? offset : 0))).selectedIndex = word.charCodeAt(offset) - 'A'.charCodeAt(0) + 1;
      }
      function savegame () {
        game = document.getElementById('game');
        if (game.options[game.selectedIndex].value == '') {
          while (name.length == 0)
            name = prompt('Enter name for new saved game:');
          game.options[0].text = name;
          game.options[0].value = name = 'words_' + name;
        } else
          name = game.options[game.selectedIndex].value;
        save = "";
        for (row = 0; row < 15; row++)
          for (col = 0; col < 15; col++)
            save += document.getElementById('board_' + row + '_' + col).value;
        hand = "";
        for (i = 0; i < 7; i++)
          hand += document.getElementById('hand_' + i).value;
        var date = new Date();
        date.setDate(date.getDate() + 365);
        document.cookie = name + '=' + save + ',' + hand + ';expires=' + date.toUTCString();
      }
      function loadgame () {
        game = document.getElementById('game');
        cookies = document.cookie.split(";");
        for (i = 0; i < cookies.length; i++) {
          name = cookies[i].substr(0, cookies[i].indexOf("="));
          value = cookies[i].substr(cookies[i].indexOf("=") + 1);
          name = name.replace(/^\s+|\s+$/g, "");
          if (name == game.options[game.selectedIndex].value) {
            parts = value.split(',');
            for (row = 0; row < 15; row++)
              for (col = 0; col < 15; col++) {
                charCode = parts[0].charCodeAt(row * 15 + col);
                document.getElementById('board_' + row + '_' + col).selectedIndex = charCode == '_'.charCodeAt(0) ? 0 : charCode - 'A'.charCodeAt(0) + 1;
              }
            for (i = 0; i < 7; i++) {
              charCode = parts[1].charCodeAt(i);
              document.getElementById('hand_' + i).selectedIndex = charCode == '_'.charCodeAt(0) ? 0 : (charCode == '*'.charCodeAt(0) ? 27 : charCode - 'A'.charCodeAt(0) + 1)
            }
          }
        }
      }
    </script>
<?php if (isset($_POST['submit'])): ?>
    <h3>Moves</h3>
    <ul style="font-family:monospace;">
<?php foreach ($solutions as $s): ?>
      <li><?php echo $s[0]; ?> <a href="javascript:void(0);" onclick="javascript:doword(<?php echo '\'' . $s[2] . '\',' . $s[3] . ',' . $s[4] . ',' . ($s[5] ? 'true' : 'false'); ?>);">&raquo;</a></li>
<?php endforeach; ?>
    </ul>
    <h3>Statistics</h3>
    <p>Words in dictionary: <?php echo count($isword); ?></p>
    <p>Hand permutations: <?php $perms_count = 0; foreach ($perms as $perm) $perms_count += count($perm); echo $perms_count; ?></p>
    <p>Valid positions: <?php echo $position_count; ?></p>
    <p>Words attempted: <?php echo $attempt_count; ?></p>
    <p>Valid moves: <?php echo count($solutions); ?></p>
    <p>Time taken: <?php echo round((microtime(true) - $start_time) * 1000) / 1000; ?>s</p>
    <p>Memory used: <?php $mem = memory_get_peak_usage(); $prefixes = array('', 'Ki', 'Mi', 'Gi'); for ($prefix = 0; $mem >= 1024; $mem = floor($mem / 102.4) / 10, $prefix++); echo $mem.' '.$prefixes[$prefix]; ?>B</p>
<?php endif; ?>
  </body>
</html>
