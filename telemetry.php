<?php
    $currentpage = "telemetry.php";
    include("header.php");
?>
  <script src="js/chart-4.4.8.js"></script>
  <style>
    canvas {
      max-width: 100%;
      max-height: 400px;
    }
    h2 {
      text-align: center;
    }
  </style>

<main id="main" class="container-fluid">
  <div class="download-notes text-center">
    <h2 class="features-title"><?php echo _('Telemetry Data'); ?></h2>
  </div>

  <div class="row justify-content-around">
    <div class="p-2 m-2 rounded text-backround col-md-5">
      <h2><?php echo _('System'); ?></h2>
      <canvas id="canvas-system"></canvas>
    </div>
    <div class="p-2 m-2 rounded text-backround col-md-5">
      <h2><?php echo _('Language'); ?></h2>
      <canvas id="canvas-language"></canvas>
    </div>
    <div class="p-2 m-2 rounded text-backround col-md-5">
      <h2><?php echo _('Theme'); ?></h2>
      <canvas id="canvas-theme"></canvas>
    </div>
    <div class="p-2 m-2 rounded text-backround col-md-5">
      <h2><?php echo _('Minor Python Version'); ?></h2>
      <canvas id="canvas-python_version__minor"></canvas>
    </div>
    <div class="p-2 m-2 rounded text-backround col-md-11">
      <h2><?php echo _('Mods'); ?></h2>
      <canvas id="canvas-mods"></canvas>
    </div>
    <div class="p-2 m-2 rounded text-backround col-md-5">
      <h2><?php echo _('Machine'); ?></h2>
      <canvas id="canvas-machine"></canvas>
    </div>
    <div class="p-2 m-2 rounded text-backround col-md-5">
      <h2><?php echo _('Default Workbench'); ?></h2>
      <canvas id="canvas-workbench_default"></canvas>
    </div>
    <div class="p-2 m-2 rounded text-backround col-md-11">
      <h2><?php echo _('Screen Resolution'); ?></h2>
      <canvas id="canvas-screen_resolution"></canvas>
    </div>
    <div class="p-2 m-2 rounded text-backround col-md-5">
      <h2><?php echo _('Toolbar Icon Size'); ?></h2>
      <canvas id="canvas-ui_toolbar_icon_size"></canvas>
    </div>
    <div class="p-2 m-2 rounded text-backround col-md-5">
      <h2><?php echo _('Navigation Style'); ?></h2>
      <canvas id="canvas-navigation_style"></canvas>
    </div>
    <div class="p-2 m-2 rounded text-backround col-md-11">
      <h2><?php echo _('Workbench Enabled'); ?></h2>
      <canvas id="canvas-workbench_enabled_list"></canvas>
    </div>
    <div class="p-2 m-2 rounded text-backround col-md-11">
      <h2><?php echo _('Workbench Disabled'); ?></h2>
      <canvas id="canvas-workbench_disabled_list"></canvas>
    </div>
  </div>
</main>

<script>
  const jsonURL = "https://www.freecad.org/posthog_events.json";

  const chartSettings = {
    "mods": { type: "bar", limit: 10, axis: 'y' },
    "python_version__minor": { type: "pie" },
    "machine": { type: "pie" },
    "screen_resolution": { type: "bar", limit: 10, axis: 'y' },
    "system": { type: "pie" },
    "ui_toolbar_icon_size": { type: "pie" },
    "theme": { type: "pie" },
    "navigation_style": { type: "pie" },
    "workbench_enabled_list": { type: "bar", limit: 10, axis: 'y' },
    "workbench_default": { type: "pie" },
    "workbench_disabled_list": { type: "bar", limit: 10, axis: 'y' },
    "language": { type: "pie" }
  };

  fetch(jsonURL)
    .then(response => response.json())
    .then(data => processEvents(data))
    .catch(error => console.error("Failed to load JSON:", error));

  function processEvents(events) {
    const userMap = {};

    events.forEach(entry => {
      const id = entry.distinct_id;
      const props = entry.properties || {};
      if (!userMap[id]) userMap[id] = {};

      for (const [key, val] of Object.entries(props)) {
        if (Array.isArray(val)) {
          if (!userMap[id][key]) userMap[id][key] = new Set(val);
          else val.forEach(v => userMap[id][key].add(v));
        } else {
          userMap[id][key] = val;
        }
      }
    });

    const aggregated = {};
    for (const props of Object.values(userMap)) {
      for (const [key, value] of Object.entries(props)) {
        if (!aggregated[key]) aggregated[key] = {};

        if (value instanceof Set) {
          for (const item of value) {
            aggregated[key][item] = (aggregated[key][item] || 0) + 1;
          }
        } else {
          aggregated[key][value] = (aggregated[key][value] || 0) + 1;
        }
      }
    }

    for (const [property, config] of Object.entries(chartSettings)) {
      if (aggregated[property]) {
        drawChartForProperty(property, aggregated[property], config);
      }
    }
  }

  function drawChartForProperty(property, dataMap, config) {
    const canvas = document.getElementById(`canvas-${property}`);
    if (!canvas) return;

    const sortedEntries = Object.entries(dataMap)
      .sort((a, b) => b[1] - a[1])
      .slice(0, config.limit || Object.entries(dataMap).length);

    const labels = sortedEntries.map(entry => entry[0]);
    const counts = sortedEntries.map(entry => entry[1]);
    const colors = getColors(labels.length);

    Chart.defaults.color = "#fff";

    new Chart(canvas.getContext("2d"), {
      type: config.type,
      data: {
        labels: labels,
        datasets: [{
          label: 'Count',
          data: counts,
          backgroundColor: colors,
          borderColor: '#fff',
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            position: 'right',
            labels: { color: '#fff' }
          },
          tooltip: {
            callbacks: {
              label: function(ctx) {
                const total = ctx.chart._metasets[0].total || ctx.dataset.data.reduce((a, b) => a + b, 0);
                const value = ctx.raw;
                const percent = ((value / total) * 100).toFixed(1);
                return `${ctx.label}: ${value} <?php echo _('users'); ?> (${percent}%)`;
              }
            }
          }
        },
        ...(config.type === 'bar' ? {
          indexAxis: config.axis || 'x',
          scales: {
            x: {
              ticks: { display: false },
            },

          }
        } : {})
      }
    });
  }

  function getColors(count) {
    const baseColors = [
      '#CB333B', '#418FDE', '#76428a', '#4BC0C0',
      '#9966FF', '#FF9F40', '#8BC34A', '#00ACC1',
      '#E91E63', '#3F51B5', '#009688', '#795548'
    ];
    return Array.from({ length: count }, (_, i) => baseColors[i % baseColors.length]);
  }
</script>
<?php include 'footer.php'; ?>
