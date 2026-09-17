import pandas as pd
import joblib

from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import (
    accuracy_score,
    classification_report,
    confusion_matrix
)

# ============================================================
# LOAD DATA
# ============================================================

data = pd.read_csv(
    "climate_training_data.csv",
    parse_dates=["timestamp"]
)

print("======================================")
print("BLOOMBOT ML TRAINING")
print("======================================")

print(
    f"Total observations: {len(data)}"
)

print()

# ============================================================
# SORT CHRONOLOGICALLY
# ============================================================

data = data.sort_values(
    "timestamp"
).reset_index(drop=True)

# ============================================================
# FEATURES
# ============================================================

features = [
    "temperature",
    "humidity",
    "wind_speed",
    "heat_index",
    "wet_bulb",
    "solar_radiation"
]

target = "risk_next_6h"

X = data[features]
y = data[target]

# ============================================================
# TIME-BASED TRAIN / TEST SPLIT
# ============================================================

# Use the first 80% for training
# and the final 20% for testing.
#
# This prevents future information from
# leaking into the training process.

split_index = int(
    len(data) * 0.80
)

X_train = X.iloc[:split_index]
X_test = X.iloc[split_index:]

y_train = y.iloc[:split_index]
y_test = y.iloc[split_index:]

print(
    f"Training samples: {len(X_train)}"
)

print(
    f"Testing samples: {len(X_test)}"
)

print()

print(
    "Training period:"
)

print(
    data["timestamp"].iloc[0]
)

print(
    "to"
)

print(
    data["timestamp"].iloc[split_index - 1]
)

print()

print(
    "Testing period:"
)

print(
    data["timestamp"].iloc[split_index]
)

print(
    "to"
)

print(
    data["timestamp"].iloc[-1]
)

# ============================================================
# TRAIN RANDOM FOREST
# ============================================================

model = RandomForestClassifier(
    n_estimators=200,
    random_state=42,
    max_depth=10,
    class_weight="balanced"
)

print()

print(
    "Training BloomBot ML model..."
)

model.fit(
    X_train,
    y_train
)

print(
    "Training complete."
)

# ============================================================
# TEST MODEL
# ============================================================

predictions = model.predict(
    X_test
)

accuracy = accuracy_score(
    y_test,
    predictions
)

print()

print("======================================")
print("BLOOMBOT ML MODEL RESULTS")
print("======================================")

print(
    f"Accuracy: {accuracy * 100:.2f}%"
)

print()

print(
    "Classification report:"
)

print(
    classification_report(
        y_test,
        predictions
    )
)

print()

print(
    "Confusion matrix:"
)

print(
    confusion_matrix(
        y_test,
        predictions
    )
)

# ============================================================
# FEATURE IMPORTANCE
# ============================================================

importance = pd.Series(
    model.feature_importances_,
    index=features
).sort_values(
    ascending=False
)

print()

print(
    "Feature importance:"
)

print(
    importance
)

# ============================================================
# SAVE MODEL
# ============================================================

joblib.dump(
    model,
    "bloom_bot_climate_model.pkl"
)

print()

print(
    "Model saved as:"
)

print(
    "bloom_bot_climate_model.pkl"
)

print()

print(
    "Training completed successfully."
)