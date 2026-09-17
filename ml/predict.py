import sys
import json
import joblib
import pandas as pd


# ==========================================
# LOAD TRAINED MODEL
# ==========================================

model = joblib.load("bloom_bot_climate_model.pkl")


# ==========================================
# READ INPUT
# ==========================================

input_data = json.loads(sys.stdin.read())


# ==========================================
# REQUIRED FEATURES
# ==========================================

features = [
    "temperature",
    "humidity",
    "wind_speed",
    "heat_index",
    "wet_bulb",
    "solar_radiation"
]


# ==========================================
# CREATE DATAFRAME
# ==========================================

values = {}

for feature in features:
    values[feature] = float(
        input_data.get(feature, 0)
    )


X = pd.DataFrame(
    [values],
    columns=features
)


# ==========================================
# MAKE PREDICTION
# ==========================================

prediction = int(
    model.predict(X)[0]
)


probabilities = model.predict_proba(X)[0]

risk_probability = float(
    probabilities[1]
)


# ==========================================
# RISK LEVEL
# ==========================================

if risk_probability >= 0.70:

    risk_level = "High"

elif risk_probability >= 0.40:

    risk_level = "Moderate"

else:

    risk_level = "Low"


# ==========================================
# RESPONSE
# ==========================================

result = {

    "status": "success",

    "prediction": prediction,

    "risk_probability":
        round(
            risk_probability * 100,
            2
        ),

    "risk_level":
        risk_level,

    "prediction_horizon":
        "Next 6 hours",

    "model":
        "BloomBot Climate Risk Model",

    "training_data":
        "Synthetic climate data"

}


print(
    json.dumps(
        result,
        indent=2
    )
)