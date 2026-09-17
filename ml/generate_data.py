import numpy as np
import pandas as pd

np.random.seed(42)

# ============================================================
# BLOOMBOT SYNTHETIC TIME-SERIES DATASET
# ============================================================

days = 120
hours = days * 24

timestamps = pd.date_range(
    start="2026-01-01",
    periods=hours,
    freq="h"
)

# ------------------------------------------------------------
# Generate realistic-ish hourly climate patterns
# ------------------------------------------------------------

hour_of_day = timestamps.hour.values
day_of_year = timestamps.dayofyear.values

# Daily temperature cycle
daily_temperature = (
    20
    + 5 * np.sin(
        2 * np.pi * (hour_of_day - 6) / 24
    )
)

# Seasonal component
seasonal_temperature = (
    2 * np.sin(
        2 * np.pi * day_of_year / 365
    )
)

temperature = (
    daily_temperature
    + seasonal_temperature
    + np.random.normal(0, 1.5, hours)
)

temperature = np.clip(
    temperature,
    8,
    35
)

# Humidity generally moves opposite temperature
humidity = (
    72
    - 0.8 * (temperature - 20)
    + np.random.normal(0, 7, hours)
)

humidity = np.clip(
    humidity,
    25,
    100
)

# Wind speed
wind_speed = np.random.exponential(
    0.7,
    hours
)

wind_speed = np.clip(
    wind_speed,
    0,
    5
)

# Heat index proxy
heat_index = (
    temperature
    + ((humidity - 50) * 0.03)
)

# Wet bulb proxy
wet_bulb = (
    temperature
    - ((100 - humidity) * 0.03)
)

# Solar radiation
solar_radiation = np.zeros(hours)

daylight = (
    (hour_of_day >= 6)
    & (hour_of_day <= 18)
)

solar_radiation[daylight] = np.clip(
    700
    * np.sin(
        np.pi
        * (hour_of_day[daylight] - 6)
        / 12
    )
    + np.random.normal(
        0,
        80,
        daylight.sum()
    ),
    0,
    1000
)

# ============================================================
# CREATE CURRENT CLIMATE RISK
# ============================================================

current_risk_score = (
    (humidity > 80) * 2
    + (wind_speed < 0.3) * 2
    + (temperature > 30) * 2
    + (temperature < 12) * 1
    + (
        (humidity > 75)
        & (wind_speed < 0.5)
    ) * 2
)

current_risk = (
    current_risk_score >= 3
).astype(int)

# ============================================================
# CREATE TRUE NEXT-6-HOUR TARGET
# ============================================================

risk_next_6h = np.zeros(hours)

for i in range(hours - 6):

    future_window = current_risk[
        i + 1:i + 7
    ]

    # If risk occurs at any point during
    # the following six hours, target = 1
    risk_next_6h[i] = int(
        np.any(future_window == 1)
    )

# Remove final six rows because
# they do not have six future hours
valid_rows = hours - 6

data = pd.DataFrame({

    "timestamp":
        timestamps[:valid_rows],

    "temperature":
        temperature[:valid_rows],

    "humidity":
        humidity[:valid_rows],

    "wind_speed":
        wind_speed[:valid_rows],

    "heat_index":
        heat_index[:valid_rows],

    "wet_bulb":
        wet_bulb[:valid_rows],

    "solar_radiation":
        solar_radiation[:valid_rows],

    "risk_next_6h":
        risk_next_6h[:valid_rows].astype(int)

})

# ============================================================
# SAVE DATASET
# ============================================================

data.to_csv(
    "climate_training_data.csv",
    index=False
)

print("======================================")
print("BLOOMBOT TIME-SERIES DATASET")
print("======================================")

print(
    f"Observations: {len(data)}"
)

print()

print(
    "Date range:"
)

print(
    data["timestamp"].min()
)

print(
    "to"
)

print(
    data["timestamp"].max()
)

print()

print(
    "Risk distribution:"
)

print(
    data["risk_next_6h"].value_counts()
)

print()

print(
    "Dataset saved as:"
)

print(
    "climate_training_data.csv"
)