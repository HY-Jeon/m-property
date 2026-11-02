import { createChart } from 'lightweight-charts';

window.realestateChartData = window.realestateChartData ?? {};

const chartContainer = document.getElementById('realestate-chart');

if (chartContainer && Object.keys(window.realestateChartData).length > 0) {
    const chart = createChart(chartContainer, {
        layout: {
            background: { color: '#ffffff' },
            textColor: '#111827',
            fontFamily: 'Inter, system-ui, sans-serif',
        },
        grid: {
            vertLines: { color: '#e5e7eb' },
            horzLines: { color: '#e5e7eb' },
        },
        rightPriceScale: {
            borderColor: '#e5e7eb',
        },
        timeScale: {
            borderColor: '#e5e7eb',
            timeVisible: true,
            secondsVisible: false,
        },
        crosshair: {
            mode: 1,
        },
    });

    const lineColors = ['#ef4444', '#2563eb', '#059669', '#f59e0b', '#8b5cf6'];
    let colorIndex = 0;

    Object.entries(window.realestateChartData).forEach(([name, data]) => {
        const series = chart.addLineSeries({
            title: name,
            color: lineColors[colorIndex % lineColors.length],
            lineWidth: 3,
        });

        series.setData(data);
        colorIndex += 1;
    });

    chart.timeScale().fitContent();

    window.addEventListener('resize', () => {
        chart.applyOptions({ width: chartContainer.clientWidth });
    });
}
