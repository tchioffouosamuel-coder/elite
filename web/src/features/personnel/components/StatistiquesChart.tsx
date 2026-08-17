import { Bar, Line } from 'react-chartjs-2'
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    Title,
    Tooltip,
    Legend,
} from 'chart.js'
import type { StatsPedagogiquesParDepartement } from '@/features/personnel/api'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, BarElement, Title, Tooltip, Legend)

interface StatistiquesChartProps {
    stats: StatsPedagogiquesParDepartement
}

export function StatistiquesChart({ stats }: StatistiquesChartProps) {
    const labels = stats.matieres.map((m) => m.nom)
    const moyennes = stats.matieres.map((m) => m.moyenne ?? 0)
    const tauxReussite = stats.matieres.map((m) => m.taux_reussite ?? 0)
    const effectifs = stats.matieres.map((m) => m.effectif_eleves)

    const dataBarMoyennes = {
        labels,
        datasets: [
            {
                label: 'Moyenne par matière',
                data: moyennes,
                backgroundColor: 'rgba(54, 162, 235, 0.6)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1,
            },
        ],
    }

    const dataLineTaux = {
        labels,
        datasets: [
            {
                label: 'Taux de réussite (%)',
                data: tauxReussite,
                borderColor: 'rgba(75, 192, 75, 1)',
                backgroundColor: 'rgba(75, 192, 75, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 5,
                pointBackgroundColor: 'rgba(75, 192, 75, 1)',
            },
        ],
    }

    const dataBarEffectifs = {
        labels,
        datasets: [
            {
                label: 'Effectif par matière',
                data: effectifs,
                backgroundColor: 'rgba(255, 193, 7, 0.6)',
                borderColor: 'rgba(255, 193, 7, 1)',
                borderWidth: 1,
            },
        ],
    }

    const options = {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'top' as const,
            },
        },
        scales: {
            y: {
                beginAtZero: true,
                max: 20,
            },
        },
    }

    const optionsTaux = {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'top' as const,
            },
        },
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
            },
        },
    }

    return (
        <div className="flex flex-col gap-8 py-4">
            <div className="rounded-lg border border-navy-100 p-6 bg-white">
                <h3 className="mb-4 text-base font-semibold text-navy-900">Moyennes par matière</h3>
                <Bar data={dataBarMoyennes} options={options} />
            </div>

            <div className="rounded-lg border border-navy-100 p-6 bg-white">
                <h3 className="mb-4 text-base font-semibold text-navy-900">Taux de réussite par matière</h3>
                <Line data={dataLineTaux} options={optionsTaux} />
            </div>

            <div className="rounded-lg border border-navy-100 p-6 bg-white">
                <h3 className="mb-4 text-base font-semibold text-navy-900">Effectifs par matière</h3>
                <Bar data={dataBarEffectifs} options={options} />
            </div>
        </div>
    )
}
