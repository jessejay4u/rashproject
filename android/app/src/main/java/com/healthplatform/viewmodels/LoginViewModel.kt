package com.healthplatform.viewmodels

import android.content.Context
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.healthplatform.data.models.LoginRequest
import com.healthplatform.data.models.DeviceInfo
import com.healthplatform.data.remote.ApiService
import com.healthplatform.utils.SecureStorage
import dagger.hilt.android.lifecycle.HiltViewModel
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

sealed class LoginUiState {
    object Idle    : LoginUiState()
    object Loading : LoginUiState()
    data class Success(val role: String) : LoginUiState()
    data class Error(val message: String) : LoginUiState()
}

@HiltViewModel
class LoginViewModel @Inject constructor(
    private val apiService:    ApiService,
    private val secureStorage: SecureStorage,
    @ApplicationContext private val context: Context
) : ViewModel() {

    private val _uiState = MutableStateFlow<LoginUiState>(LoginUiState.Idle)
    val uiState: StateFlow<LoginUiState> = _uiState

    fun login(email: String, password: String) {
        if (email.isBlank() || password.isBlank()) {
            _uiState.value = LoginUiState.Error("Email and password are required.")
            return
        }

        viewModelScope.launch {
            _uiState.value = LoginUiState.Loading

            try {
                val deviceId   = secureStorage.getOrCreateDeviceId()
                val request    = LoginRequest(
                    email      = email.trim(),
                    password   = password,
                    deviceInfo = DeviceInfo(
                        deviceId  = deviceId,
                        userAgent = "HealthPlatform-Android/${android.os.Build.VERSION.RELEASE}"
                    )
                )

                val response = apiService.login(request)

                if (response.isSuccessful && response.body()?.success == true) {
                    val data = response.body()!!.data!!
                    secureStorage.saveTokens(data.accessToken, data.refreshToken)
                    secureStorage.saveUser(data.user)
                    _uiState.value = LoginUiState.Success(data.user.roleName)
                } else {
                    val errMsg = response.body()?.message ?: "Login failed. Check your credentials."
                    _uiState.value = LoginUiState.Error(errMsg)
                }
            } catch (e: Exception) {
                val msg = when {
                    e.message?.contains("Unable to resolve host") == true ->
                        "No network connection. Please check your internet."
                    e.message?.contains("timeout") == true ->
                        "Connection timed out. Please try again."
                    else -> "Login failed: ${e.localizedMessage}"
                }
                _uiState.value = LoginUiState.Error(msg)
            }
        }
    }

    fun resetState() {
        _uiState.value = LoginUiState.Idle
    }
}
