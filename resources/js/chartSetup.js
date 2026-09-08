import {
    ArcElement,
    BarElement,
    CategoryScale,
    Chart,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    Tooltip,
} from 'chart.js';

Chart.register(ArcElement, BarElement, CategoryScale, LinearScale, LineElement, PointElement, Legend, Tooltip);
